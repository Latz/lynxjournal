import { execSync } from 'child_process';
import constants from '../../constants.json' with { type: 'json' };

const baseURL = process.env.WP_BASE_URL ?? constants.WP_ENV.BASE_URL;

export default async function globalSetup() {
    try {
        const res = await fetch(baseURL);
        if (res.ok || res.status === 301 || res.status === 302) return;
    } catch {
        // Not reachable — start wp-env.
    }
    console.log('[global-setup] Starting wp-env...');
    execSync('npm run env:start', { stdio: 'inherit' });
}
