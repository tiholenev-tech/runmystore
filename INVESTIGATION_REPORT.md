# INVESTIGATION — life-board sees only 1-2 signals/day

Дата: 01.05.2026
Контекст: Тихол вижда само 1-2 сигнала на ден в life-board.php. Очаква десетки.

## ХИПОТЕЗА
compute-insights.php:235 default module=products, но life-board търси home. 99% от сигналите не стигат.

## REALITY CHECK
Старите 6 pf функции (lines 1384,1435,1475,1525,1581,1626) сетват module=home ИЗРИЧНО. Хипотезата е частично грешна — бъгът е в новите S89 функции.

## DIAGNOSTIC ЗА S91
1. Сетват ли S89 функции module=home? (pfOrderStaleNoDelivery, pfPaymentDueReminder, pfNewSupplierFirstDelivery, pfVolumeDiscountDetected, pfDeliveryAnomalyPattern, pfStockoutRiskReduction)
2. SELECT module, COUNT(*) FROM ai_insights WHERE tenant_id=7 GROUP BY module
3. life-board WHERE clause - точна
4. helpers.php:161 cooldown + helpers.php:170 urgency limits (2/3/3) suppression?

## ОПЦИИ ЗА FIX
A. Fix module за 6-те S89 функции (НИСЪК risk, препоръчителен)
B. Default 'home' (СРЕДЕН risk)
C. life-board чете multiple modules (НИСЪК)
D. Махни cooldown (ВИСОК — само тест)
E. Свали plan gate (бизнес решение)
F. Релакс urgency limits (може спам)
G. Investigation първо (НУЛЕВ — ПРЕПОРЪЧИТЕЛЕН)

## ПРЕПОРЪКА
1. Diag 1+2 на droplet
2. Прилагай Опция A (90% вероятност)
3. НЕ прилагай B, D, F без одобрение
