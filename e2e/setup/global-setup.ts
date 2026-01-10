import { FullConfig } from '@playwright/test';
import { execSync } from 'child_process';

async function globalSetup(config: FullConfig) {
  console.log('\n🚀 E2E Global Setup: Preparing clean test environment...');

  try {
    // Clean database state
    console.log('🗑️  Cleaning test database...');
    const useDocker = process.env.E2E_DOCKER_DISABLED !== '1';
    const commandPrefix = useDocker ? 'docker compose exec -T app ' : '';
    const envFlag = process.env.CI ? '--env=test' : '';

    // Reset database to clean state
    execSync(`${commandPrefix}php bin/console doctrine:database:drop --force --if-exists ${envFlag}`, { stdio: 'inherit' });
    execSync(`${commandPrefix}php bin/console doctrine:database:create --if-not-exists ${envFlag}`, { stdio: 'inherit' });
    execSync(`${commandPrefix}php bin/console doctrine:migrations:migrate --no-interaction ${envFlag}`, { stdio: 'inherit' });

    console.log('✨ Test environment ready with clean database state');

  } catch (error) {
    console.error('❌ Failed to prepare test environment:', error);
    throw error;
  }
}

export default globalSetup;