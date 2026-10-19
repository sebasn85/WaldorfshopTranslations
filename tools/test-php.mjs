import { getPHPLoaderModule } from './php-runtime/node_modules/@php-wasm/node-8-3/index.js';
import { PHP, loadPHPRuntime } from './php-runtime/node_modules/@php-wasm/universal/index.js';
import fs from 'node:fs/promises';
import path from 'node:path';
const root = path.resolve(import.meta.dirname, '..');
const php = new PHP(await loadPHPRuntime(await getPHPLoaderModule()));
await php.mkdir('/app');
const files = [];
async function copy(dir) {
  await php.mkdir('/app/' + dir);
  for (const entry of await fs.readdir(path.join(root, dir), { withFileTypes: true })) {
    const relative = path.posix.join(dir, entry.name);
    if (entry.isDirectory()) await copy(relative);
    else if (entry.name.endsWith('.php')) {
      await php.writeFile('/app/' + relative, await fs.readFile(path.join(root, relative)));
      files.push(relative);
    }
  }
}
for (const dir of ['src', 'tests', 'resources']) await copy(dir);
const lint = await php.run({ code: '<?php $files=json_decode(' + JSON.stringify(JSON.stringify(files)) + ',true); foreach($files as $file){token_get_all(file_get_contents("/app/".$file),TOKEN_PARSE);} echo count($files)." PHP files parsed successfully\\n";' });
console.log(lint.text);
if (lint.exitCode) process.exit(lint.exitCode);
const result = await php.run({ scriptPath: '/app/tests/run.php' });
console.log(result.text);
process.exitCode = result.exitCode;
