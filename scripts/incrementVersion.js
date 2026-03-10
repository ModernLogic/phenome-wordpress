/* eslint-disable @typescript-eslint/no-var-requires */
const fs = require('fs/promises');
const util = require('util');
const exec = util.promisify(require('child_process').exec);
const path = require('path');

const ROOT = path.join(__dirname, '..');
const THEME_STYLE_CSS = path.join(
  ROOT,
  'wp-content/themes/Avada-Child-Theme/style.css'
);
const PLUGIN_PHP = path.join(
  ROOT,
  'wp-content/plugins/phenome-woo-order-migration/phenome-woo-order-migration.php'
);

function parseSemver(version) {
  const versionPart = (version || '').split('+')[0].trim();
  const parts = versionPart.split('.').map((n) => parseInt(n, 10) || 0);
  return {
    major: parts[0] ?? 0,
    minor: parts[1] ?? 0,
    patch: parts[2] ?? 0,
  };
}

function compareSemver(a, b) {
  const aParsed = parseSemver(a);
  const bParsed = parseSemver(b);
  if (aParsed.major !== bParsed.major) return aParsed.major - bParsed.major;
  if (aParsed.minor !== bParsed.minor) return aParsed.minor - bParsed.minor;
  return aParsed.patch - bParsed.patch;
}

function formatVersion(versionObj) {
  return `${versionObj.major}.${versionObj.minor}.${versionObj.patch}`;
}

function extractVersionFromStyleCss(content) {
  const m = content.match(/Version:\s*([\d.]+)/i);
  return m ? m[1].trim() : null;
}

function extractVersionFromPluginPhp(content) {
  const header = content.match(/\*\s*Version:\s*([\d.]+)/);
  const define = content.match(/define\s*\(\s*['"]PHENOME_WOO_MIGRATION_VERSION['"]\s*,\s*['"]([^'"]+)['"]\s*\)/);
  return header ? header[1].trim() : define ? define[1].trim() : null;
}

const main = async () => {
  const command = process.argv[2] ?? 'build';

  if (!new Set(['build', 'hotfix', 'minor', 'major']).has(command)) {
    console.error('INVALID: pass one of [build hotfix minor major]');
    console.info('received: ', command);
    process.exit(-1);
  }

  console.log('Checking for uncommitted files');
  try {
    await exec('git update-index --refresh 2>/dev/null || true');
    await exec('git diff-index --quiet HEAD --');
  } catch (e) {
    console.error('ERROR: There are uncommitted changes');
    console.log('Commit or revert changed files first');
    process.exit(-1);
  }

  const styleContent = await fs.readFile(THEME_STYLE_CSS, 'utf-8');
  const pluginContent = await fs.readFile(PLUGIN_PHP, 'utf-8');

  const themeVersion = extractVersionFromStyleCss(styleContent);
  const pluginVersion = extractVersionFromPluginPhp(pluginContent);

  if (!themeVersion || !pluginVersion) {
    console.error('ERROR: Could not parse version from theme style.css or plugin PHP');
    process.exit(-1);
  }

  console.log('Current versions:');
  console.log(`  Theme (style.css): ${themeVersion}`);
  console.log(`  Plugin: ${pluginVersion}`);

  const maxVersion =
    compareSemver(themeVersion, pluginVersion) >= 0 ? themeVersion : pluginVersion;
  console.log(`Version to bump: ${maxVersion}`);

  const parsed = parseSemver(maxVersion);
  const newVersion = (() => {
    switch (command) {
      case 'build':
        return formatVersion({ ...parsed, patch: parsed.patch + 1 });
      case 'hotfix':
        return formatVersion({ ...parsed, patch: parsed.patch + 1 });
      case 'minor':
        return formatVersion({
          major: parsed.major,
          minor: parsed.minor + 1,
          patch: 0,
        });
      case 'major':
        return formatVersion({
          major: parsed.major + 1,
          minor: 0,
          patch: 0,
        });
      default:
        return maxVersion;
    }
  })();

  console.log(`New version: ${newVersion}`);

  const updatedStyle = styleContent.replace(
    /(Version:\s*)[\d.]+/i,
    `$1${newVersion}`
  );
  await fs.writeFile(THEME_STYLE_CSS, updatedStyle);

  let updatedPlugin = pluginContent.replace(
    /\*\s*Version:\s*[\d.]+/,
    `* Version: ${newVersion}`
  );
  updatedPlugin = updatedPlugin.replace(
    /(define\s*\(\s*['"]PHENOME_WOO_MIGRATION_VERSION['"]\s*,\s*['"])[^'"]+(['"]\s*\))/,
    `$1${newVersion}$2`
  );
  await fs.writeFile(PLUGIN_PHP, updatedPlugin);

  console.log('All versions updated successfully');
  console.log(`Committing version ${newVersion}...`);

  await exec(`git add "${THEME_STYLE_CSS}" "${PLUGIN_PHP}"`);
  await exec(`git commit -m "Build ${newVersion}"`);

  console.log('Version update complete!');
};

main().catch((error) => {
  console.error('Error:', error);
  process.exit(-1);
});
