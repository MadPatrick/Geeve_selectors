// ESLint (zie .github/workflows/checks.yml): vangt echte fouten in de eigen JavaScript, zoals onbekende variabelen,
// dubbele sleutels en onbereikbare code. Geen opmaakregels. Vendor-bestanden (xlsx, JsBarcode) worden overgeslagen.
// Lokaal: npx eslint .
import js from '@eslint/js';
import globals from 'globals';

export default [
  { ignores: ['**/vendor/**', 'data/**', 'tmp/**', 'node_modules/**'] },
  js.configs.recommended,
  {
    files: ['**/*.js'],
    languageOptions: {
      ecmaVersion: 2020,
      sourceType: 'script',
      // ecmaVersion 2020 is ook een browsercheck: nieuwere syntax (zoals ||=) geeft een fout, zie CLAUDE.md.
      globals: {
        ...globals.browser,
        // Gedeelde scripts uit shared/ en vendor-bibliotheken die via <script> geladen worden.
        GeeveEsc: 'readonly',
        GeeveProgress: 'readonly',
        GeeveEnterTop: 'readonly',
        ArtSticker: 'readonly',
        XLSX: 'readonly',
        JsBarcode: 'readonly',
      },
    },
    rules: {
      // Opruimpunten, geen fouten: zichtbaar als waarschuwing, de check blijft groen.
      'no-unused-vars': ['warn', { args: 'none', caughtErrors: 'none' }],
      'no-useless-escape': 'warn',
      'no-prototype-builtins': 'warn',
      'no-empty': ['error', { allowEmptyCatch: true }],
    },
  },
  {
    files: ['tools/**/*.js'],
    languageOptions: { globals: { ...globals.node } },
  },
];
