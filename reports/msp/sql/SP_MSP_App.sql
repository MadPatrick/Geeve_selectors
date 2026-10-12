-- =============================================================================================
-- dbo.SP_MSP_App  -  kopie van dbo.SP_MSP voor de webapp (Reports > MSP report)
--
-- Verschil met dbo.SP_MSP: stap 06 (het weggeschreven vaste tabel
-- [005_data_geeve].dbo.Temp_Export_MSP_factuurregels) is weggelaten; het resultaat komt rechtstreeks uit
-- de tijdelijke tabel #Temp_Uitkomst_factuurregels. De rest (stappen 01 t/m 05) is ongewijzigd, ook de
-- selectie van de vorige maand. De PRINT-regels zijn weggelaten.
-- Zo heeft het app-account (GeeveSlangkaart) alleen EXECUTE nodig en geen schrijfrechten in de database,
-- en blijft dbo.SP_MSP (voor het Excel-bestand "MSP CLAIM") volledig ongewijzigd.
--
-- Uit te voeren door een beheerder (rechten: CREATE PROCEDURE in 005_Data_Geeve).
-- =============================================================================================
USE [005_Data_Geeve];
GO

CREATE OR ALTER PROCEDURE [dbo].[SP_MSP_App]
AS
BEGIN
SET NOCOUNT ON;

---- 01 Drop
If OBJECT_ID(N'tempdb..#temp_invoer') IS NOT NULL BEGIN DROP TABLE #temp_invoer END
If OBJECT_ID(N'tempdb..#Temp_Bron_leeg') IS NOT NULL BEGIN DROP TABLE #Temp_Bron_leeg END ;
If OBJECT_ID(N'tempdb..#Temp_BR_leeg') IS NOT NULL BEGIN DROP TABLE #Temp_BR_leeg END ;

DECLARE @datumvan varchar(10),
  @VanDatum datetime,
  @TotDatum datetime

SET @VanDatum = CAST(GETDATE() AS date)
SET @datumvan = CONVERT(varchar(10), @VanDatum, 103)

---- 02 INPUT
If OBJECT_ID(N'tempdb..#temp_invoer') IS NOT NULL BEGIN DROP TABLE #temp_invoer END
SELECT * INTO #temp_invoer
FROM (
SELECT
  '' as inv_order_ID
,  CASE WHEN MONTH(@VanDatum) = 1 THEN YEAR(@VanDatum)-1 ELSE  YEAR(@VanDatum) END AS inv_jaar
,  CASE WHEN MONTH(@VanDatum) = 1 THEN MONTH(@VanDatum)+12 ELSE  MONTH(@VanDatum) END AS inv_maand
,  'P' AS inv_Type
,  '1' AS inv_Checked
,   1  AS inv_BlockItem
,   1 AS inv_Reviewed
,  '230981%' AS inv_oms25
) #temp_invoer
;

---- 03 Bron
If OBJECT_ID(N'tempdb..#Temp_Bron_factuurregels') IS NOT NULL BEGIN DROP TABLE #Temp_Bron_factuurregels END ;
SELECT * INTO #Temp_Bron_factuurregels
FROM (
SELECT fhs.faknr AS fhs_faknr, fhs.fakdat, fhs.artcode,art.itemprod, fhs.regel AS fhs_regel, fhs.esr_aantal AS fhs_esr_aantal, fhs.bdr_ev_ed_val AS fhs_bdr_ev_ed_val
, i.Assortment AS i_Assortment
, c.TextField11
, ia.ItemCode AS ia_ItemCode_ha, ia.ItemCodeAccount AS ia_ItemCodeAccount_ha
, art.itemprod AS art_itemprod, art.itemreq AS art_itemreq
, art.aantal AS art_aantal_per_stuk
, art.aantal * ISNULL(fhs.esr_aantal,0) AS art_aantal
, IAOD_ItemCode_OD, IAOD_ItemCodeAccount_OD
, round((st_prijs83 * art.aantal * ISNULL(fhs.esr_aantal,0)),2) AS bedrag_OD
, CASE WHEN day(fhs.fakdat) < 10 THEN CONVERT(varchar(3), '0' + CONVERT(varchar(1), day(fhs.fakdat))  )     ELSE CONVERT(varchar(2), (''+ day(fhs.fakdat)) ) END fakday
, CASE WHEN month(fhs.fakdat) < 10 THEN CONVERT(varchar(3), '0' + CONVERT(varchar(1), month(fhs.fakdat))  )     ELSE CONVERT(varchar(2), (''+ month(fhs.fakdat)) ) END fakmonth
FROM [005].dbo.frhsrg AS fhs WITH (NOLOCK)
LEFT JOIN [005].dbo.frhkrg AS fhk WITH (NOLOCK)
ON fhs.faknr = fhk.faknr
LEFT JOIN [005].dbo.items AS I WITH (NOLOCK)
ON fhs.artcode = i.ItemCode
LEFT JOIN [005].dbo.ItemAccounts AS IA WITH (NOLOCK)
ON fhs.artcode = IA.ItemCode and ia.crdnr = '100011'
LEFT JOIN [005].dbo.cicmpy AS C WITH (NOLOCK)
ON fhk.fakdebnr = c.debnr
LEFT JOIN
(SELECT itemprod, itemreq, IAOD_ItemCode_OD, IAOD_ItemCodeAccount_OD, st_prijs83, sum(q_o) as aantal
FROM
(
SELECT r.itemprod, r.itemreq, count(r.itemreq) as q_rgl, sum(quantity) AS q_o
, r.MainVersion
, r.sequenceno
, r.version
, st.prijs83 AS st_prijs83
, IAOD.ItemCode AS IAOD_ItemCode_OD, IAOD.ItemCodeAccount AS IAOD_ItemCodeAccount_OD
, ROW_NUMBER() OVER(PARTITION BY r.itemprod ORDER BY r.itemprod) as linenumber
, ( SELECT MainVersion
 FROM [005].dbo.recipe AS h WITH (NOLOCK)
 WHERE r.itemprod = h.itemprod AND h.itemreq is null AND h.sequenceno = '0' AND MainVersion = '1 ' AND r.version =  Version
 ) as mainv_kop
, ( SELECT Version
 FROM [005].dbo.recipe AS h WITH (NOLOCK)
 WHERE r.itemprod = h.itemprod AND h.itemreq is null AND h.sequenceno = '0' AND MainVersion = '1' AND r.version =  Version
 ) as Version_kop
FROM [005].dbo.recipe AS r WITH (NOLOCK)
LEFT JOIN [005].dbo.ItemAccounts AS IAOD WITH (NOLOCK)
ON r.itemreq = IAOD.ItemCode and IAOD.crdnr = '100011'
LEFT JOIN [005].dbo.staffl AS st WITH (NOLOCK)
ON  r.itemreq = st.artcode AND st.prijslijst = 'salesprice'
WHERE 1 = 1
AND itemreq is not null
GROUP BY r.itemprod,r.version,r.itemreq, r.MainVersion,r.sequenceno, version,IAOD.ItemCode, IAOD.ItemCodeAccount, st.prijs83
) as regels
WHERE 1 = 1
AND mainv_kop is not null
GROUP BY itemprod, itemreq, IAOD_ItemCode_OD, IAOD_ItemCodeAccount_OD, st_prijs83
) AS art
ON fhs.artcode = art.itemprod
CROSS JOIN #temp_invoer inv
WHERE 1 = 1
AND ar_soort <> inv.inv_Type
AND year(fhs.fakdat) = inv.inv_jaar
AND MONTH(fhs.fakdat) = (inv.inv_maand-1)
AND c.TextField11 IS NOT NULL
AND TextField11 is not null
AND LTRIM(ISNULL(fhs.artcode,'')) <> 'PORTO'
AND LTRIM(ISNULL(fhs.artcode,'')) NOT LIKE 'CERTIFICAAT%'
AND LTRIM(ISNULL(fhs.artcode,'')) NOT LIKE 'ZD-2%'
AND LTRIM(ISNULL(art.itemreq,'')) <> 'PORTO'
AND LTRIM(ISNULL(art.itemreq,'')) NOT LIKE 'CERTIFICAAT%'
AND LTRIM(ISNULL(art.itemreq,'')) NOT LIKE 'ZD-2%'
AND ISNULL(fhs.esr_aantal,0) >= 0
AND (
  (art.itemprod IS NULL     AND ia.ItemCode IS NOT NULL)
  OR (art.itemprod IS NOT NULL AND art.IAOD_ItemCode_OD IS NOT NULL)
 )
) Temp_Bron_factuurregels
;

---- 04 BR Bedrijfsresultaten
If OBJECT_ID(N'tempdb..#Temp_BR_factuurregels') IS NOT NULL BEGIN DROP TABLE #Temp_BR_factuurregels END ;
SELECT * INTO #Temp_BR_factuurregels
FROM (
SELECT TextField11 AS [End Customer Number]
, CASE  WHEN artcode = itemprod AND itemprod IS NOT NULL AND IAOD_ItemCodeAccount_OD IS NOT NULL THEN IAOD_ItemCodeAccount_OD
  WHEN artcode = itemprod AND itemprod IS NOT NULL AND IAOD_ItemCodeAccount_OD IS NULL AND art_itemreq IS NOT NULL THEN art_itemreq
  WHEN artcode <> itemprod AND itemprod IS NOT NULL AND artcode IS NOT NULL THEN ISNULL(NULLIF(LTRIM(RTRIM(ia_ItemCodeAccount_ha)),''), artcode)
  WHEN artcode <> isnull(itemprod,0) AND itemprod IS NULL AND artcode IS NOT NULL THEN ISNULL(NULLIF(LTRIM(RTRIM(ia_ItemCodeAccount_ha)),''), artcode)
  ELSE '' END AS [Part Number]
, CASE  WHEN artcode = itemprod AND itemprod IS NOT NULL AND IAOD_ItemCodeAccount_OD IS NOT NULL THEN art_aantal
  WHEN artcode = itemprod AND itemprod IS NOT NULL AND IAOD_ItemCodeAccount_OD IS NULL AND art_itemreq IS NOT NULL THEN art_aantal
  WHEN artcode <> itemprod AND itemprod IS NOT NULL AND artcode IS NOT NULL THEN fhs_esr_aantal
  WHEN artcode <> isnull(itemprod,0) AND itemprod IS NULL AND artcode IS NOT NULL THEN fhs_esr_aantal
  ELSE '' END AS qty
, CASE  WHEN artcode = itemprod AND itemprod IS NOT NULL AND IAOD_ItemCodeAccount_OD IS NOT NULL THEN bedrag_OD
  WHEN artcode = itemprod AND itemprod IS NOT NULL AND IAOD_ItemCodeAccount_OD IS NULL AND art_itemreq IS NOT NULL THEN bedrag_OD
  WHEN artcode <> itemprod AND itemprod IS NOT NULL AND artcode IS NOT NULL THEN fhs_bdr_ev_ed_val
  WHEN artcode <> isnull(itemprod,0) AND itemprod IS NULL AND artcode IS NOT NULL THEN fhs_bdr_ev_ed_val
  ELSE 0 END AS [Invoice Price]
, fhs_faknr AS [Invoice Number]
, '' AS [ ]
, CONVERT(VARCHAR(10), '' + fakday + '/' + fakmonth + '/' + CONVERT(VARCHAR(4), year(fakdat))) AS [Invoice Date (DD/MM/YYYY)]
, '' as dagen, fakdat
, i_Assortment AS FINartgrp
FROM #Temp_Bron_factuurregels WITH (NOLOCK)
) AS Temp_BR_factuurregels
;

---- 05 Uitkomst tabel
If OBJECT_ID(N'tempdb..#Temp_Uitkomst_factuurregels') IS NOT NULL BEGIN DROP TABLE #Temp_Uitkomst_factuurregels END ;
SELECT * INTO #Temp_Uitkomst_factuurregels
FROM (
SELECT        [End Customer Number], [Part Number]
,             CEILING(ROUND(qty * 2, 6)) / 2.0 AS qty
,             [Invoice Price], [Invoice Number], [ ], [Invoice Date (DD/MM/YYYY)], dagen, fakdat, FINartgrp
FROM           #Temp_BR_factuurregels AS MSC WITH (NOLOCK)
WHERE          qty >= 0
) AS Temp_Uitkomst_factuurregels
;

---- 06 Resultaat (zonder vaste tabel): dezelfde kolommen en volgorde als dbo.SP_MSP
SELECT        [End Customer Number], [Part Number], qty, [Invoice Number], [Invoice Date (DD/MM/YYYY)], dagen, fakdat, FINartgrp, [Invoice Price], [ ]
FROM           #Temp_Uitkomst_factuurregels
ORDER BY fakdat DESC
END
GO

-- Rechten voor de webapp (database + uitvoeren). Eenmalig:
IF NOT EXISTS (SELECT 1 FROM sys.database_principals WHERE name = 'GeeveSlangkaart')
    CREATE USER [GeeveSlangkaart] FOR LOGIN [GeeveSlangkaart];
GRANT EXECUTE ON OBJECT::dbo.SP_MSP_App TO [GeeveSlangkaart];
GO
