-- ============================================================
-- TheSportsDB - producao (schema + vinculos). Niveis 1 e 2.
-- Roda direto no MySQL (sem migrate). Tudo keyado por sigla.
-- ============================================================

-- 0) SCHEMA: novas colunas + indices unicos (equivale as migrations)
ALTER TABLE `selecoes`
  ADD COLUMN `id_externo` BIGINT UNSIGNED NULL AFTER `sigla`,
  ADD UNIQUE KEY `selecoes_torneio_id_id_externo_unique` (`torneio_id`, `id_externo`);

ALTER TABLE `jogos`
  ADD COLUMN `id_evento_externo` BIGINT UNSIGNED NULL AFTER `status`,
  ADD UNIQUE KEY `jogos_id_evento_externo_unique` (`id_evento_externo`);

-- (Opcional) Para o Laravel nao tentar reaplicar essas migrations depois,
-- registre-as na tabela migrations com o proximo batch (ajuste o numero):
--   INSERT INTO migrations (migration, batch) VALUES
--     ('2026_06_22_120000_add_id_externo_to_selecoes_table', 99),
--     ('2026_06_22_130000_add_id_evento_externo_to_jogos_table', 99);

-- 1) Corrige os 6 placeholders A Definir (renomeia + vincula)
UPDATE selecoes SET nome='Bosnia e Herzegovina', sigla='BIH', slug='bosnia-e-herzegovina', id_externo=134510 WHERE sigla='UA1';
UPDATE selecoes SET nome='Suecia', sigla='SWE', slug='suecia', id_externo=133916 WHERE sigla='UB2';
UPDATE selecoes SET nome='Turquia', sigla='TUR', slug='turquia', id_externo=135985 WHERE sigla='UC3';
UPDATE selecoes SET nome='Tchequia', sigla='CZE', slug='tchequia', id_externo=133904 WHERE sigla='UD4';
UPDATE selecoes SET nome='RD Congo', sigla='COD', slug='rd-congo', id_externo=136475 WHERE sigla='IC1';
UPDATE selecoes SET nome='Iraque', sigla='IRQ', slug='iraque', id_externo=140148 WHERE sigla='IC2';

-- 2) Vincula as demais selecoes (por sigla)
UPDATE selecoes SET id_externo=134497 WHERE sigla='MEX';
UPDATE selecoes SET id_externo=136482 WHERE sigla='RSA';
UPDATE selecoes SET id_externo=134517 WHERE sigla='KOR';
UPDATE selecoes SET id_externo=140073 WHERE sigla='CAN';
UPDATE selecoes SET id_externo=136472 WHERE sigla='QAT';
UPDATE selecoes SET id_externo=134506 WHERE sigla='SUI';
UPDATE selecoes SET id_externo=134496 WHERE sigla='BRA';
UPDATE selecoes SET id_externo=136139 WHERE sigla='MAR';
UPDATE selecoes SET id_externo=140175 WHERE sigla='HAI';
UPDATE selecoes SET id_externo=136450 WHERE sigla='SCO';
UPDATE selecoes SET id_externo=134514 WHERE sigla='USA';
UPDATE selecoes SET id_externo=136471 WHERE sigla='PAR';
UPDATE selecoes SET id_externo=134500 WHERE sigla='AUS';
UPDATE selecoes SET id_externo=133907 WHERE sigla='GER';
UPDATE selecoes SET id_externo=140271 WHERE sigla='CUW';
UPDATE selecoes SET id_externo=134502 WHERE sigla='CIV';
UPDATE selecoes SET id_externo=134507 WHERE sigla='ECU';
UPDATE selecoes SET id_externo=133905 WHERE sigla='NED';
UPDATE selecoes SET id_externo=134503 WHERE sigla='JPN';
UPDATE selecoes SET id_externo=136142 WHERE sigla='TUN';
UPDATE selecoes SET id_externo=134515 WHERE sigla='BEL';
UPDATE selecoes SET id_externo=136138 WHERE sigla='EGY';
UPDATE selecoes SET id_externo=134511 WHERE sigla='IRN';
UPDATE selecoes SET id_externo=137449 WHERE sigla='NZL';
UPDATE selecoes SET id_externo=133909 WHERE sigla='ESP';
UPDATE selecoes SET id_externo=136477 WHERE sigla='CPV';
UPDATE selecoes SET id_externo=136137 WHERE sigla='KSA';
UPDATE selecoes SET id_externo=134504 WHERE sigla='URU';
UPDATE selecoes SET id_externo=133913 WHERE sigla='FRA';
UPDATE selecoes SET id_externo=136143 WHERE sigla='SEN';
UPDATE selecoes SET id_externo=136516 WHERE sigla='NOR';
UPDATE selecoes SET id_externo=134509 WHERE sigla='ARG';
UPDATE selecoes SET id_externo=134516 WHERE sigla='ALG';
UPDATE selecoes SET id_externo=135986 WHERE sigla='AUT';
UPDATE selecoes SET id_externo=140145 WHERE sigla='JOR';
UPDATE selecoes SET id_externo=133908 WHERE sigla='POR';
UPDATE selecoes SET id_externo=140151 WHERE sigla='UZB';
UPDATE selecoes SET id_externo=134501 WHERE sigla='COL';
UPDATE selecoes SET id_externo=133914 WHERE sigla='ENG';
UPDATE selecoes SET id_externo=133912 WHERE sigla='CRO';
UPDATE selecoes SET id_externo=134513 WHERE sigla='GHA';
UPDATE selecoes SET id_externo=136141 WHERE sigla='PAN';

-- 3) Vincula os 72 jogos da fase de grupos aos eventos (por par de siglas)
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391728 WHERE ((m.sigla='MEX' AND v.sigla='RSA') OR (m.sigla='RSA' AND v.sigla='MEX'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2461103 WHERE ((m.sigla='KOR' AND v.sigla='CZE') OR (m.sigla='CZE' AND v.sigla='KOR'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2461104 WHERE ((m.sigla='CAN' AND v.sigla='BIH') OR (m.sigla='BIH' AND v.sigla='CAN'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391729 WHERE ((m.sigla='USA' AND v.sigla='PAR') OR (m.sigla='PAR' AND v.sigla='USA'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391731 WHERE ((m.sigla='HAI' AND v.sigla='SCO') OR (m.sigla='SCO' AND v.sigla='HAI'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2461105 WHERE ((m.sigla='AUS' AND v.sigla='TUR') OR (m.sigla='TUR' AND v.sigla='AUS'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391730 WHERE ((m.sigla='BRA' AND v.sigla='MAR') OR (m.sigla='MAR' AND v.sigla='BRA'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391732 WHERE ((m.sigla='QAT' AND v.sigla='SUI') OR (m.sigla='SUI' AND v.sigla='QAT'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391734 WHERE ((m.sigla='CIV' AND v.sigla='ECU') OR (m.sigla='ECU' AND v.sigla='CIV'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391733 WHERE ((m.sigla='GER' AND v.sigla='CUW') OR (m.sigla='CUW' AND v.sigla='GER'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391735 WHERE ((m.sigla='NED' AND v.sigla='JPN') OR (m.sigla='JPN' AND v.sigla='NED'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2461106 WHERE ((m.sigla='SWE' AND v.sigla='TUN') OR (m.sigla='TUN' AND v.sigla='SWE'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391738 WHERE ((m.sigla='KSA' AND v.sigla='URU') OR (m.sigla='URU' AND v.sigla='KSA'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391739 WHERE ((m.sigla='ESP' AND v.sigla='CPV') OR (m.sigla='CPV' AND v.sigla='ESP'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391737 WHERE ((m.sigla='IRN' AND v.sigla='NZL') OR (m.sigla='NZL' AND v.sigla='IRN'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391736 WHERE ((m.sigla='BEL' AND v.sigla='EGY') OR (m.sigla='EGY' AND v.sigla='BEL'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391742 WHERE ((m.sigla='FRA' AND v.sigla='SEN') OR (m.sigla='SEN' AND v.sigla='FRA'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2461107 WHERE ((m.sigla='IRQ' AND v.sigla='NOR') OR (m.sigla='NOR' AND v.sigla='IRQ'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391740 WHERE ((m.sigla='ARG' AND v.sigla='ALG') OR (m.sigla='ALG' AND v.sigla='ARG'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391741 WHERE ((m.sigla='AUT' AND v.sigla='JOR') OR (m.sigla='JOR' AND v.sigla='AUT'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391744 WHERE ((m.sigla='GHA' AND v.sigla='PAN') OR (m.sigla='PAN' AND v.sigla='GHA'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391743 WHERE ((m.sigla='ENG' AND v.sigla='CRO') OR (m.sigla='CRO' AND v.sigla='ENG'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2461108 WHERE ((m.sigla='POR' AND v.sigla='COD') OR (m.sigla='COD' AND v.sigla='POR'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391745 WHERE ((m.sigla='UZB' AND v.sigla='COL') OR (m.sigla='COL' AND v.sigla='UZB'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2461109 WHERE ((m.sigla='CZE' AND v.sigla='RSA') OR (m.sigla='RSA' AND v.sigla='CZE'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2461110 WHERE ((m.sigla='SUI' AND v.sigla='BIH') OR (m.sigla='BIH' AND v.sigla='SUI'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391746 WHERE ((m.sigla='CAN' AND v.sigla='QAT') OR (m.sigla='QAT' AND v.sigla='CAN'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391747 WHERE ((m.sigla='MEX' AND v.sigla='KOR') OR (m.sigla='KOR' AND v.sigla='MEX'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391748 WHERE ((m.sigla='BRA' AND v.sigla='HAI') OR (m.sigla='HAI' AND v.sigla='BRA'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391749 WHERE ((m.sigla='SCO' AND v.sigla='MAR') OR (m.sigla='MAR' AND v.sigla='SCO'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2461111 WHERE ((m.sigla='TUR' AND v.sigla='PAR') OR (m.sigla='PAR' AND v.sigla='TUR'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391750 WHERE ((m.sigla='USA' AND v.sigla='AUS') OR (m.sigla='AUS' AND v.sigla='USA'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391752 WHERE ((m.sigla='GER' AND v.sigla='CIV') OR (m.sigla='CIV' AND v.sigla='GER'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391751 WHERE ((m.sigla='ECU' AND v.sigla='CUW') OR (m.sigla='CUW' AND v.sigla='ECU'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2461112 WHERE ((m.sigla='NED' AND v.sigla='SWE') OR (m.sigla='SWE' AND v.sigla='NED'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391753 WHERE ((m.sigla='TUN' AND v.sigla='JPN') OR (m.sigla='JPN' AND v.sigla='TUN'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391757 WHERE ((m.sigla='URU' AND v.sigla='CPV') OR (m.sigla='CPV' AND v.sigla='URU'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391756 WHERE ((m.sigla='ESP' AND v.sigla='KSA') OR (m.sigla='KSA' AND v.sigla='ESP'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391754 WHERE ((m.sigla='BEL' AND v.sigla='IRN') OR (m.sigla='IRN' AND v.sigla='BEL'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391755 WHERE ((m.sigla='NZL' AND v.sigla='EGY') OR (m.sigla='EGY' AND v.sigla='NZL'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391760 WHERE ((m.sigla='NOR' AND v.sigla='SEN') OR (m.sigla='SEN' AND v.sigla='NOR'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2461113 WHERE ((m.sigla='FRA' AND v.sigla='IRQ') OR (m.sigla='IRQ' AND v.sigla='FRA'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391758 WHERE ((m.sigla='ARG' AND v.sigla='AUT') OR (m.sigla='AUT' AND v.sigla='ARG'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391759 WHERE ((m.sigla='JOR' AND v.sigla='ALG') OR (m.sigla='ALG' AND v.sigla='JOR'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391761 WHERE ((m.sigla='ENG' AND v.sigla='GHA') OR (m.sigla='GHA' AND v.sigla='ENG'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391762 WHERE ((m.sigla='PAN' AND v.sigla='CRO') OR (m.sigla='CRO' AND v.sigla='PAN'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391763 WHERE ((m.sigla='POR' AND v.sigla='UZB') OR (m.sigla='UZB' AND v.sigla='POR'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2461114 WHERE ((m.sigla='COL' AND v.sigla='COD') OR (m.sigla='COD' AND v.sigla='COL'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391765 WHERE ((m.sigla='SCO' AND v.sigla='BRA') OR (m.sigla='BRA' AND v.sigla='SCO'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391764 WHERE ((m.sigla='MAR' AND v.sigla='HAI') OR (m.sigla='HAI' AND v.sigla='MAR'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391767 WHERE ((m.sigla='SUI' AND v.sigla='CAN') OR (m.sigla='CAN' AND v.sigla='SUI'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2461115 WHERE ((m.sigla='BIH' AND v.sigla='QAT') OR (m.sigla='QAT' AND v.sigla='BIH'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2461116 WHERE ((m.sigla='CZE' AND v.sigla='MEX') OR (m.sigla='MEX' AND v.sigla='CZE'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391766 WHERE ((m.sigla='RSA' AND v.sigla='KOR') OR (m.sigla='KOR' AND v.sigla='RSA'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391768 WHERE ((m.sigla='CUW' AND v.sigla='CIV') OR (m.sigla='CIV' AND v.sigla='CUW'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391769 WHERE ((m.sigla='ECU' AND v.sigla='GER') OR (m.sigla='GER' AND v.sigla='ECU'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2461117 WHERE ((m.sigla='JPN' AND v.sigla='SWE') OR (m.sigla='SWE' AND v.sigla='JPN'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391771 WHERE ((m.sigla='TUN' AND v.sigla='NED') OR (m.sigla='NED' AND v.sigla='TUN'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2461118 WHERE ((m.sigla='TUR' AND v.sigla='USA') OR (m.sigla='USA' AND v.sigla='TUR'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391770 WHERE ((m.sigla='PAR' AND v.sigla='AUS') OR (m.sigla='AUS' AND v.sigla='PAR'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391775 WHERE ((m.sigla='NOR' AND v.sigla='FRA') OR (m.sigla='FRA' AND v.sigla='NOR'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2461119 WHERE ((m.sigla='SEN' AND v.sigla='IRQ') OR (m.sigla='IRQ' AND v.sigla='SEN'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391773 WHERE ((m.sigla='EGY' AND v.sigla='IRN') OR (m.sigla='IRN' AND v.sigla='EGY'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391774 WHERE ((m.sigla='NZL' AND v.sigla='BEL') OR (m.sigla='BEL' AND v.sigla='NZL'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391772 WHERE ((m.sigla='CPV' AND v.sigla='KSA') OR (m.sigla='KSA' AND v.sigla='CPV'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391776 WHERE ((m.sigla='URU' AND v.sigla='ESP') OR (m.sigla='ESP' AND v.sigla='URU'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391781 WHERE ((m.sigla='PAN' AND v.sigla='ENG') OR (m.sigla='ENG' AND v.sigla='PAN'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391779 WHERE ((m.sigla='CRO' AND v.sigla='GHA') OR (m.sigla='GHA' AND v.sigla='CRO'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391777 WHERE ((m.sigla='ALG' AND v.sigla='AUT') OR (m.sigla='AUT' AND v.sigla='ALG'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391780 WHERE ((m.sigla='JOR' AND v.sigla='ARG') OR (m.sigla='ARG' AND v.sigla='JOR'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2391778 WHERE ((m.sigla='COL' AND v.sigla='POR') OR (m.sigla='POR' AND v.sigla='COL'));
UPDATE jogos j JOIN selecoes m ON m.id=j.selecao_mandante_id JOIN selecoes v ON v.id=j.selecao_visitante_id SET j.id_evento_externo=2461120 WHERE ((m.sigla='COD' AND v.sigla='UZB') OR (m.sigla='UZB' AND v.sigla='COD'));

