import fs from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const projectRoot = path.resolve(__dirname, '..');
const distDir = path.join(projectRoot, 'dist');
const pluginDir = path.join(projectRoot, 'wordpress', 'aac-member-portal');
const pluginAppDir = path.join(pluginDir, 'app');
const pluginZipPath = path.join(projectRoot, 'wordpress', 'aac-member-portal.zip');
const execFileAsync = promisify(execFile);
const EXTRA_PLUGIN_ASSETS = [];

const STABLE_ASSET_ALIASES = {
  js: 'portal-app.js',
  css: 'portal-app.css',
};

const REQUIRED_STABLE_MEDIA_ALIASES = [
  {
    pattern: /^join-hero-static-image-[^.]+\.jpg$/,
    alias: 'join-hero-static-image.jpg',
  },
];

async function ensureDirectoryExists(targetPath) {
  try {
    await fs.access(targetPath);
  } catch {
    throw new Error(`Required path not found: ${targetPath}`);
  }
}

async function createCompatibilityAliases() {
  const assetsDir = path.join(pluginAppDir, 'assets');
  const assetEntries = await fs.readdir(assetsDir);
  const latestJs = assetEntries.find((entry) => /^index-[^.]+\.js$/.test(entry));
  const latestCss = assetEntries.find((entry) => /^index-[^.]+\.css$/.test(entry));

  if (latestJs) {
    await fs.copyFile(path.join(assetsDir, latestJs), path.join(assetsDir, STABLE_ASSET_ALIASES.js));
  }

  if (latestCss) {
    await fs.copyFile(path.join(assetsDir, latestCss), path.join(assetsDir, STABLE_ASSET_ALIASES.css));
  }

  await Promise.all(REQUIRED_STABLE_MEDIA_ALIASES.map(async ({ pattern, alias }) => {
    const source = assetEntries.find((entry) => pattern.test(entry));
    if (!source || source === alias) {
      return;
    }

    await fs.copyFile(path.join(assetsDir, source), path.join(assetsDir, alias));
  }));
}

async function createPluginZip() {
  await fs.rm(pluginZipPath, { force: true });
  const stagingRoot = await fs.mkdtemp(path.join(os.tmpdir(), 'aac-member-portal-package-'));
  const stagedPluginDir = path.join(stagingRoot, path.basename(pluginDir));

  try {
    await fs.cp(pluginDir, stagedPluginDir, {
      recursive: true,
      filter: (source) => {
        const name = path.basename(source);
        return !name.endsWith('.zip') && name !== '.DS_Store' && !name.startsWith('._');
      },
    });
    await execFileAsync('ditto', ['-c', '-k', '--norsrc', '--keepParent', stagedPluginDir, pluginZipPath], {
      cwd: stagingRoot,
    });
  } finally {
    await fs.rm(stagingRoot, { recursive: true, force: true });
  }
}

async function copyExtraPluginAssets() {
  await Promise.all(
    EXTRA_PLUGIN_ASSETS.map(async ({ source, target }) => {
      await fs.mkdir(path.dirname(target), { recursive: true });
      await fs.copyFile(source, target);
    }),
  );
}

async function removeUnusedPluginAssets() {
  await Promise.all([
    fs.rm(path.join(pluginAppDir, 'login-hero-uploaded-video.mp4'), { force: true }),
  ]);
}

async function main() {
  await ensureDirectoryExists(distDir);
  await ensureDirectoryExists(pluginDir);

  await fs.rm(pluginAppDir, { recursive: true, force: true });
  await fs.mkdir(pluginAppDir, { recursive: true });
  await fs.cp(distDir, pluginAppDir, { recursive: true });
  await copyExtraPluginAssets();
  await createCompatibilityAliases();
  await removeUnusedPluginAssets();
  await createPluginZip();

  console.log(`WordPress plugin assets copied to ${pluginAppDir}`);
  console.log(`Plugin ready at ${pluginDir}`);
  console.log(`Plugin zip updated at ${pluginZipPath}`);
}

main().catch((error) => {
  console.error(error.message);
  process.exitCode = 1;
});
