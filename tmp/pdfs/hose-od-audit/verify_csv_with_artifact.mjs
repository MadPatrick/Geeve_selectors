import fs from "node:fs";
import { Workbook } from "file:///C:/Users/patrick/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/@oai/artifact-tool/dist/artifact_tool.mjs";

const csvPath = "hoses/data/artikelnummers_accessoires.csv";
const mapping = new Map(Object.entries({
  "0442-06": "21,2",
  "0442-08": "24,3",
  "0442-10": "28",
  "0442-12": "32",
  "0442-16": "39,5",
  "0442-20": "50,8",
  "0442-24": "57,2",
  "0446-12": "32",
  "0446-16": "38,4",
  "0446-20": "45,8",
  "0446-24": "53,5",
  "0446-32": "68",
  "0447-10": "26,8",
  "0447-12": "32",
  "0447-16": "38,7",
  "0447-20": "49,7",
  "0447-24": "57,8",
  "0447-32": "72",
  "0449-16": "38,7",
  "H31-20": "50,8",
  "2580N-06V12": "21,6",
  "2580N-08V12": "25",
  "424-32": "64",
  "F42-12": "31,9",
  "F42-16": "38,5",
  "F42-20": "50",
}));

function parseLine(line, delimiter = ";") {
  const fields = [];
  let field = "";
  let quoted = false;
  for (let i = 0; i < line.length; i += 1) {
    const char = line[i];
    if (char === '"') {
      if (quoted && line[i + 1] === '"') {
        field += '"';
        i += 1;
      } else {
        quoted = !quoted;
      }
    } else if (char === delimiter && !quoted) {
      fields.push(field);
      field = "";
    } else {
      field += char;
    }
  }
  fields.push(field);
  return fields;
}

function csvEscape(value) {
  const text = String(value ?? "");
  return /[",\r\n]/.test(text) ? `"${text.replaceAll('"', '""')}"` : text;
}

async function saveBlob(blob, path) {
  const bytes = Buffer.from(await blob.arrayBuffer());
  fs.writeFileSync(path, bytes);
}

const text = fs.readFileSync(csvPath, "utf8").replace(/^\uFEFF/, "");
const rows = text.split(/\r?\n/).filter((line) => line.length > 0).map((line) => parseLine(line));
if (rows[0].length !== 14 || rows[0][4] !== "Buitenmaat slang (mm)") {
  throw new Error(`Unexpected header: ${JSON.stringify(rows[0])}`);
}
const commaCsv = rows.map((row) => row.map(csvEscape).join(",")).join("\n");
const workbook = await Workbook.fromCSV(commaCsv, { sheetName: "Accessoires" });
const sheet = workbook.worksheets.getItem("Accessoires");

const before = [];
for (let i = 1; i < rows.length; i += 1) {
  const article = rows[i][0];
  if (!mapping.has(article)) continue;
  if (rows[i][4] !== "") throw new Error(`${article} already has outside diameter ${rows[i][4]}`);
  before.push([i + 1, article, rows[i][4]]);
}
if (before.length !== mapping.size) {
  const found = new Set(before.map((entry) => entry[1]));
  const missing = [...mapping.keys()].filter((key) => !found.has(key));
  throw new Error(`Mapping rows not found: ${missing.join(", ")}`);
}

await saveBlob(await workbook.render({ sheet: "Accessoires", range: "A837:F868", format: "png", scale: 1.25 }), "tmp/pdfs/hose-od-audit/csv-before.png");

if (process.argv.includes("--preflight")) {
  console.log(JSON.stringify({ mode: "preflight", targetCount: before.length, sample: before.slice(0, 5) }, null, 2));
  process.exit(0);
}

for (const [rowNumber, article] of before) {
  sheet.getRange(`E${rowNumber}`).values = [[mapping.get(article)]];
}

for (const [rowNumber, article] of before) {
  const value = sheet.getRange(`E${rowNumber}`).values[0][0];
  if (String(value) !== mapping.get(article)) {
    throw new Error(`Artifact edit mismatch for ${article}: ${value}`);
  }
}

await saveBlob(await workbook.render({ sheet: "Accessoires", range: "A837:F868", format: "png", scale: 1.25 }), "tmp/pdfs/hose-od-audit/csv-after.png");
console.log(JSON.stringify({ mode: "edited", targetCount: before.length, first: before[0], last: before.at(-1) }, null, 2));
