const fs = require('fs');
const filePath = 'dist/bankcsm-ui/browser/index.html';
const isProduction = process.argv.includes('--prod');

fs.readFile(filePath, 'utf8', (err, data) => {
  if (err) throw err;
  const newBase = isProduction ? '<base href="/bankcsm-ui/browser/">' : '<base href="/">';
  const result = data.replace(/<base href=".*?">/, newBase);

  fs.writeFile(filePath, result, 'utf8', (err) => {
    if (err) throw err;
    console.log(`Base href set to ${newBase}`);
  });
});
