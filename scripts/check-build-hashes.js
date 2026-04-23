require('fs').readdirSync('public/dist/js', { withFileTypes: true })
  .filter(f => f.isFile() && f.name.endsWith('.js'))
  .forEach(f => {
    const hash = f.name.match(/-([a-f0-9]+)\.js$/)?.[1] || '';
    console.log(`${f.name}: ${hash.slice(0,8)}`);
  });