<?php
/**
 * /tax/TaxEngineInterface.php
 * 
 * Strategy pattern за tax engines per country
 * 
 * Day 1: BG active. Други държави = GenericTaxEngine (no tax features).
 * Phase 2: RO, GR, DE activate-вате.
 */

interface TaxEngineInterface {
    
    /**
     * Annual tax estimate based on YTD data
     * @return array ['available' => bool, 'gross_income' => float, 'npr_amount' => float,
     *                'taxable_base' => float, 'social_security' => float, 'tax_amount' => float,
     *                'effective_rate' => float]
     */
    public function estimateAnnualTax(): array;
    
    /**
     * VAT threshold check + alert
     * @return array ['available' => bool, 'status' => string, 'pct_of_threshold' => float,
     *                'threshold' => float, 'remaining' => float, 'projected_cross_month' => ?int]
     */
    public function checkVATThreshold(): array;
    
    /**
     * Upcoming tax reminders (next 30 days)
     * @return array of ['type' => string, 'date' => string, 'amount' => ?float, 'message' => string]
     */
    public function getReminders(): array;
    
    /**
     * Annual declaration calendar
     * @return array ['declaration_deadline' => string, 'quarterly_advances' => string[]]
     */
    public function getDeclarationCalendar(int $year): array;
    
    /**
     * Is this engine supported for current tenant
     */
    public function isAvailable(): bool;
    
    /**
     * Country code (BG, RO, etc.)
     */
    public function getCountryCode(): string;
}


/**
 * Factory — returns правилния engine based on tenant.tax_jurisdiction
 */
class TaxEngineFactory {
    
    public static function for(array $tenant, PDO $pdo): TaxEngineInterface {
        $country = $tenant['tax_jurisdiction'] ?? 'BG';
        
        // Load country config
        $stmt = $pdo->prepare("SELECT * FROM country_config WHERE country_code = ?");
        $stmt->execute([$country]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$config || !$config['active']) {
            return new GenericTaxEngine($tenant, $pdo, $config);
        }
        
        $class = $config['tax_engine_class'] ?? 'GenericTaxEngine';
        
        return match($class) {
            'BGTaxEngine' => new BGTaxEngine($tenant, $pdo, $config),
            'ROTaxEngine' => class_exists('ROTaxEngine') ? new ROTaxEngine($tenant, $pdo, $config) : new GenericTaxEngine($tenant, $pdo, $config),
            'GRTaxEngine' => class_exists('GRTaxEngine') ? new GRTaxEngine($tenant, $pdo, $config) : new GenericTaxEngine($tenant, $pdo, $config),
            'DETaxEngine' => class_exists('DETaxEngine') ? new DETaxEngine($tenant, $pdo, $config) : new GenericTaxEngine($tenant, $pdo, $config),
            default => new GenericTaxEngine($tenant, $pdo, $config),
        };
    }
}


/**
 * BGTaxEngine — Day 1 implementation
 * Базиран на Bible §44
 */
class BGTaxEngine implements TaxEngineInterface {
    
    private array $tenant;
    private PDO $pdo;
    private array $config;
    
    public function __construct(array $tenant, PDO $pdo, array $config) {
        $this->tenant = $tenant;
        $this->pdo = $pdo;
        $this->config = $config;
    }
    
    public function isAvailable(): bool { return true; }
    public function getCountryCode(): string { return 'BG'; }
    
    public function estimateAnnualTax(): array {
        $year = (int)date('Y');
        $tenant_id = $this->tenant['id'];
        
        // YTD income
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) AS income
            FROM money_movements 
            WHERE tenant_id = ? AND direction = 'in' AND is_business = TRUE
              AND YEAR(occurred_at) = ?
        ");
        $stmt->execute([$tenant_id, $year]);
        $income_ytd = (float)$stmt->fetchColumn();
        
        $months_elapsed = max(1, (int)date('n'));
        $projected_annual = $income_ytd * (12 / $months_elapsed);
        
        // НПР percentage based on profession template
        $npr_rates = json_decode($this->config['npr_rates_json'] ?: '{}', true);
        $template_code = $this->tenant['profession_template'] ?? 'personal';
        
        $npr_pct = match(true) {
            in_array($template_code, ['it_digital','health_therapy','teachers_trainers','photo_video','consultants_legal']) => 25,
            in_array($template_code, ['craftsman_tech','beauty_care']) => 40,
            $template_code === 'agriculture' => 60,
            default => 0,
        };
        
        $npr_amount = $projected_annual * ($npr_pct / 100);
        $taxable_before_ss = $projected_annual - $npr_amount;
        
        // Social security (БГ 2026)
        $monthly_base = max(
            $this->config['social_security_min_eur'] ?: 551,
            min($this->config['social_security_max_eur'] ?: 1918, $projected_annual / 12)
        );
        $annual_ss = $monthly_base * ($this->config['social_security_rate'] ?: 0.278) * 12;
        
        $taxable_after_ss = max(0, $taxable_before_ss - $annual_ss);
        $tax_rate = ($this->config['income_tax_rate'] ?: 10) / 100;
        $tax = $taxable_after_ss * $tax_rate;
        
        return [
            'available' => true,
            'gross_income' => round($projected_annual, 2),
            'npr_pct' => $npr_pct,
            'npr_amount' => round($npr_amount, 2),
            'taxable_before_ss' => round($taxable_before_ss, 2),
            'social_security' => round($annual_ss, 2),
            'taxable_after_ss' => round($taxable_after_ss, 2),
            'tax_amount' => round($tax, 2),
            'effective_rate' => $projected_annual > 0 ? round(($tax / $projected_annual) * 100, 2) : 0,
            'months_elapsed' => $months_elapsed,
        ];
    }
    
    public function checkVATThreshold(): array {
        $threshold = (float)($this->config['vat_threshold_eur'] ?: 51130);
        $year = (int)date('Y');
        $tenant_id = $this->tenant['id'];
        
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) AS revenue
            FROM money_movements 
            WHERE tenant_id = ? AND direction = 'in' AND is_business = TRUE
              AND YEAR(occurred_at) = ?
        ");
        $stmt->execute([$tenant_id, $year]);
        $revenue_ytd = (float)$stmt->fetchColumn();
        
        $pct = ($revenue_ytd / $threshold) * 100;
        $months_elapsed = max(1, (int)date('n'));
        $projected = $revenue_ytd * (12 / $months_elapsed);
        $projected_pct = ($projected / $threshold) * 100;
        
        $status = 'safe';
        $projected_cross_month = null;
        
        if ($pct >= 100) {
            $status = 'crossed';
        } elseif ($projected_pct >= 100) {
            $status = 'projected_cross';
            // When will user cross?
            $monthly_rate = $revenue_ytd / $months_elapsed;
            $remaining = $threshold - $revenue_ytd;
            $months_to_cross = $monthly_rate > 0 ? ceil($remaining / $monthly_rate) : 999;
            $projected_cross_month = (int)date('n') + $months_to_cross;
        } elseif ($pct >= 70) {
            $status = 'approaching';
        }
        
        return [
            'available' => true,
            'status' => $status,
            'revenue_ytd' => round($revenue_ytd, 2),
            'threshold' => $threshold,
            'pct_of_threshold' => round($pct, 1),
            'projected_annual' => round($projected, 2),
            'projected_pct' => round($projected_pct, 1),
            'remaining' => round(max(0, $threshold - $revenue_ytd), 2),
            'projected_cross_month' => $projected_cross_month,
        ];
    }
    
    public function getReminders(): array {
        $reminders = [];
        $today = new DateTime('now', new DateTimeZone($this->tenant['timezone'] ?? 'Europe/Sofia'));
        
        // Месечни осигуровки (25-то число)
        $next_ss = clone $today;
        if ((int)$today->format('d') > 25) {
            $next_ss->modify('first day of next month')->setDate(
                (int)$next_ss->format('Y'), (int)$next_ss->format('m'), 25
            );
        } else {
            $next_ss->setDate((int)$today->format('Y'), (int)$today->format('m'), 25);
        }
        $days_to_ss = (int)$today->diff($next_ss)->days;
        if ($days_to_ss <= 7) {
            $reminders[] = [
                'type' => 'monthly_ss',
                'date' => $next_ss->format('Y-m-d'),
                'days_left' => $days_to_ss,
                'message_key' => 'tax.monthly_social_security',
                'message_vars' => ['month' => $today->format('F')],
            ];
        }
        
        // Тримесечни аванси
        $quarterly = json_decode($this->config['quarterly_advance_deadlines'] ?: '[]', true);
        foreach ($quarterly as $md) {
            [$m, $d] = explode('-', $md);
            $deadline = new DateTime("{$today->format('Y')}-{$m}-{$d}", new DateTimeZone($this->tenant['timezone'] ?? 'Europe/Sofia'));
            if ($deadline < $today) continue;
            $days = (int)$today->diff($deadline)->days;
            if ($days <= 14) {
                $quarter = match($m) {
                    '04' => 'Q1', '07' => 'Q2', '10' => 'Q3', '01' => 'Q4', default => 'Q?'
                };
                $reminders[] = [
                    'type' => 'quarterly_advance',
                    'date' => $deadline->format('Y-m-d'),
                    'days_left' => $days,
                    'message_key' => 'tax.quarterly_advance',
                    'message_vars' => ['quarter' => $quarter, 'date' => $deadline->format('d.m.Y')],
                ];
            }
        }
        
        // Годишна декларация (30 април)
        $decl_md = $this->config['declaration_deadline_md'] ?: '04-30';
        [$m, $d] = explode('-', $decl_md);
        $next_year = (int)$today->format('Y') + ((int)$today->format('m') > (int)$m ? 1 : 0);
        $decl_date = new DateTime("{$next_year}-{$m}-{$d}", new DateTimeZone($this->tenant['timezone'] ?? 'Europe/Sofia'));
        $days = (int)$today->diff($decl_date)->days;
        if ($days <= 30) {
            $reminders[] = [
                'type' => 'annual_declaration',
                'date' => $decl_date->format('Y-m-d'),
                'days_left' => $days,
                'message_key' => 'tax.declaration_deadline',
                'message_vars' => ['days' => $days, 'date' => $decl_date->format('d.m.Y')],
            ];
        }
        
        return $reminders;
    }
    
    public function getDeclarationCalendar(int $year): array {
        return [
            'declaration_deadline' => $year + 1 . '-' . ($this->config['declaration_deadline_md'] ?: '04-30'),
            'quarterly_advances' => json_decode($this->config['quarterly_advance_deadlines'] ?: '[]', true),
        ];
    }
}


/**
 * GenericTaxEngine — fallback за всички unsupported countries
 * Връща no-op values + i18n message за "идва скоро"
 */
class GenericTaxEngine implements TaxEngineInterface {
    
    private array $tenant;
    
    public function __construct(array $tenant, PDO $pdo, ?array $config = null) {
        $this->tenant = $tenant;
    }
    
    public function isAvailable(): bool { return false; }
    public function getCountryCode(): string { return $this->tenant['tax_jurisdiction'] ?? '??'; }
    
    public function estimateAnnualTax(): array {
        return [
            'available' => false,
            'message_key' => 'tax.not_supported_country',
            'country' => $this->getCountryCode(),
        ];
    }
    
    public function checkVATThreshold(): array {
        return [
            'available' => false,
            'status' => 'not_supported',
            'message_key' => 'tax.not_supported_country',
        ];
    }
    
    public function getReminders(): array {
        return [];
    }
    
    public function getDeclarationCalendar(int $year): array {
        return [
            'available' => false,
            'declaration_deadline' => null,
            'quarterly_advances' => [],
        ];
    }
}
