-- MSP-factuurregels: dezelfde berekening als dbo.SP_MSP (stappen 01 t/m 05), maar als één lees-query
-- (CTE's) zonder tijdelijke of vaste tabellen. Draait in database 005 (Exact); er wordt niets weggeschreven.
-- Peildatum: vandaag; geselecteerd worden de facturen van de VORIGE maand (zoals in de procedure).
WITH inv AS (
    -- 02 INPUT
    SELECT
        'P' AS inv_Type,
        CASE WHEN MONTH(CAST(GETDATE() AS date)) = 1 THEN YEAR(CAST(GETDATE() AS date)) - 1 ELSE YEAR(CAST(GETDATE() AS date)) END AS inv_jaar,
        CASE WHEN MONTH(CAST(GETDATE() AS date)) = 1 THEN MONTH(CAST(GETDATE() AS date)) + 12 ELSE MONTH(CAST(GETDATE() AS date)) END AS inv_maand
),
bron AS (
    -- 03 Bron
    SELECT fhs.faknr AS fhs_faknr, fhs.fakdat, fhs.artcode, art.itemprod, fhs.regel AS fhs_regel,
           fhs.esr_aantal AS fhs_esr_aantal, fhs.bdr_ev_ed_val AS fhs_bdr_ev_ed_val,
           i.Assortment AS i_Assortment,
           c.TextField11,
           ia.ItemCode AS ia_ItemCode_ha, ia.ItemCodeAccount AS ia_ItemCodeAccount_ha,
           art.itemprod AS art_itemprod, art.itemreq AS art_itemreq,
           art.aantal AS art_aantal_per_stuk,
           art.aantal * ISNULL(fhs.esr_aantal, 0) AS art_aantal,
           IAOD_ItemCode_OD, IAOD_ItemCodeAccount_OD,
           ROUND((st_prijs83 * art.aantal * ISNULL(fhs.esr_aantal, 0)), 2) AS bedrag_OD,
           CASE WHEN DAY(fhs.fakdat) < 10 THEN CONVERT(varchar(3), '0' + CONVERT(varchar(1), DAY(fhs.fakdat))) ELSE CONVERT(varchar(2), ('' + DAY(fhs.fakdat))) END AS fakday,
           CASE WHEN MONTH(fhs.fakdat) < 10 THEN CONVERT(varchar(3), '0' + CONVERT(varchar(1), MONTH(fhs.fakdat))) ELSE CONVERT(varchar(2), ('' + MONTH(fhs.fakdat))) END AS fakmonth
    FROM dbo.frhsrg AS fhs WITH (NOLOCK)
    LEFT JOIN dbo.frhkrg AS fhk WITH (NOLOCK) ON fhs.faknr = fhk.faknr
    LEFT JOIN dbo.items AS i WITH (NOLOCK) ON fhs.artcode = i.ItemCode
    LEFT JOIN dbo.ItemAccounts AS ia WITH (NOLOCK) ON fhs.artcode = ia.ItemCode AND ia.crdnr = '100011'
    LEFT JOIN dbo.cicmpy AS c WITH (NOLOCK) ON fhk.fakdebnr = c.debnr
    LEFT JOIN (
        SELECT itemprod, itemreq, IAOD_ItemCode_OD, IAOD_ItemCodeAccount_OD, st_prijs83, SUM(q_o) AS aantal
        FROM (
            SELECT r.itemprod, r.itemreq, COUNT(r.itemreq) AS q_rgl, SUM(quantity) AS q_o,
                   r.MainVersion, r.sequenceno, r.version,
                   st.prijs83 AS st_prijs83,
                   IAOD.ItemCode AS IAOD_ItemCode_OD, IAOD.ItemCodeAccount AS IAOD_ItemCodeAccount_OD,
                   ROW_NUMBER() OVER (PARTITION BY r.itemprod ORDER BY r.itemprod) AS linenumber,
                   (SELECT MainVersion FROM dbo.recipe AS h WITH (NOLOCK)
                     WHERE r.itemprod = h.itemprod AND h.itemreq IS NULL AND h.sequenceno = '0' AND MainVersion = '1 ' AND r.version = Version) AS mainv_kop,
                   (SELECT Version FROM dbo.recipe AS h WITH (NOLOCK)
                     WHERE r.itemprod = h.itemprod AND h.itemreq IS NULL AND h.sequenceno = '0' AND MainVersion = '1' AND r.version = Version) AS Version_kop
            FROM dbo.recipe AS r WITH (NOLOCK)
            LEFT JOIN dbo.ItemAccounts AS IAOD WITH (NOLOCK) ON r.itemreq = IAOD.ItemCode AND IAOD.crdnr = '100011'
            LEFT JOIN dbo.staffl AS st WITH (NOLOCK) ON r.itemreq = st.artcode AND st.prijslijst = 'salesprice'
            WHERE 1 = 1
              AND itemreq IS NOT NULL
            GROUP BY r.itemprod, r.version, r.itemreq, r.MainVersion, r.sequenceno, version, IAOD.ItemCode, IAOD.ItemCodeAccount, st.prijs83
        ) AS regels
        WHERE 1 = 1
          AND mainv_kop IS NOT NULL
        GROUP BY itemprod, itemreq, IAOD_ItemCode_OD, IAOD_ItemCodeAccount_OD, st_prijs83
    ) AS art ON fhs.artcode = art.itemprod
    CROSS JOIN inv
    WHERE 1 = 1
      AND ar_soort <> inv.inv_Type
      AND YEAR(fhs.fakdat) = inv.inv_jaar
      AND MONTH(fhs.fakdat) = (inv.inv_maand - 1)
      AND c.TextField11 IS NOT NULL
      AND LTRIM(ISNULL(fhs.artcode, '')) <> 'PORTO'
      AND LTRIM(ISNULL(fhs.artcode, '')) NOT LIKE 'CERTIFICAAT%'
      AND LTRIM(ISNULL(fhs.artcode, '')) NOT LIKE 'ZD-2%'
      AND LTRIM(ISNULL(art.itemreq, '')) <> 'PORTO'
      AND LTRIM(ISNULL(art.itemreq, '')) NOT LIKE 'CERTIFICAAT%'
      AND LTRIM(ISNULL(art.itemreq, '')) NOT LIKE 'ZD-2%'
      AND ISNULL(fhs.esr_aantal, 0) >= 0
      AND (
            (art.itemprod IS NULL AND ia.ItemCode IS NOT NULL)
         OR (art.itemprod IS NOT NULL AND art.IAOD_ItemCode_OD IS NOT NULL)
      )
),
br AS (
    -- 04 BR Bedrijfsresultaten
    SELECT TextField11 AS [End Customer Number],
           CASE WHEN artcode = itemprod AND itemprod IS NOT NULL AND IAOD_ItemCodeAccount_OD IS NOT NULL THEN IAOD_ItemCodeAccount_OD
                WHEN artcode = itemprod AND itemprod IS NOT NULL AND IAOD_ItemCodeAccount_OD IS NULL AND art_itemreq IS NOT NULL THEN art_itemreq
                WHEN artcode <> itemprod AND itemprod IS NOT NULL AND artcode IS NOT NULL THEN ISNULL(NULLIF(LTRIM(RTRIM(ia_ItemCodeAccount_ha)), ''), artcode)
                WHEN artcode <> ISNULL(itemprod, 0) AND itemprod IS NULL AND artcode IS NOT NULL THEN ISNULL(NULLIF(LTRIM(RTRIM(ia_ItemCodeAccount_ha)), ''), artcode)
                ELSE '' END AS [Part Number],
           CASE WHEN artcode = itemprod AND itemprod IS NOT NULL AND IAOD_ItemCodeAccount_OD IS NOT NULL THEN art_aantal
                WHEN artcode = itemprod AND itemprod IS NOT NULL AND IAOD_ItemCodeAccount_OD IS NULL AND art_itemreq IS NOT NULL THEN art_aantal
                WHEN artcode <> itemprod AND itemprod IS NOT NULL AND artcode IS NOT NULL THEN fhs_esr_aantal
                WHEN artcode <> ISNULL(itemprod, 0) AND itemprod IS NULL AND artcode IS NOT NULL THEN fhs_esr_aantal
                ELSE '' END AS qty,
           CASE WHEN artcode = itemprod AND itemprod IS NOT NULL AND IAOD_ItemCodeAccount_OD IS NOT NULL THEN bedrag_OD
                WHEN artcode = itemprod AND itemprod IS NOT NULL AND IAOD_ItemCodeAccount_OD IS NULL AND art_itemreq IS NOT NULL THEN bedrag_OD
                WHEN artcode <> itemprod AND itemprod IS NOT NULL AND artcode IS NOT NULL THEN fhs_bdr_ev_ed_val
                WHEN artcode <> ISNULL(itemprod, 0) AND itemprod IS NULL AND artcode IS NOT NULL THEN fhs_bdr_ev_ed_val
                ELSE 0 END AS [Invoice Price],
           fhs_faknr AS [Invoice Number],
           '' AS [ ],
           CONVERT(VARCHAR(10), '' + fakday + '/' + fakmonth + '/' + CONVERT(VARCHAR(4), YEAR(fakdat))) AS [Invoice Date (DD/MM/YYYY)],
           '' AS dagen, fakdat,
           i_Assortment AS FINartgrp
    FROM bron
),
uitkomst AS (
    -- 05 Uitkomst: qty naar boven afgerond op halve stuks, negatieve aantallen eruit
    SELECT [End Customer Number], [Part Number],
           CEILING(ROUND(qty * 2, 6)) / 2.0 AS qty,
           [Invoice Price], [Invoice Number], [ ], [Invoice Date (DD/MM/YYYY)], dagen, fakdat, FINartgrp
    FROM br
    WHERE qty >= 0
)
-- 06 Resultaat: zelfde kolommen en volgorde als dbo.SP_MSP
SELECT [End Customer Number], [Part Number], qty, [Invoice Number], [Invoice Date (DD/MM/YYYY)], dagen, fakdat, FINartgrp, [Invoice Price], [ ]
FROM uitkomst
ORDER BY fakdat DESC
