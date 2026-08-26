import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'org.americanalpineclub.memberportal',
  appName: 'AAC Member Portal',
  webDir: 'dist',
  server: {
    androidScheme: 'https',
  },
};

export default config;
