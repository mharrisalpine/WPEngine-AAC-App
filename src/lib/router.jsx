import React from 'react';
import { BrowserRouter, HashRouter } from 'react-router-dom';
import { getAppRuntimeConfig } from '@/lib/backendConfig';

const getRouterMode = () => {
  const runtimeConfig = getAppRuntimeConfig();

  if (runtimeConfig.routerMode) {
    return runtimeConfig.routerMode;
  }

  if (typeof window !== 'undefined' && window.location.hash.startsWith('#/')) {
    return 'hash';
  }

  if (import.meta.env.VITE_ROUTER_MODE) {
    return import.meta.env.VITE_ROUTER_MODE;
  }

  if (import.meta.env.VITE_APP_RUNTIME === 'mobile') {
    return 'hash';
  }

  return 'browser';
};

export const AppRouter = ({ children }) => {
  const RouterComponent = getRouterMode() === 'hash' ? HashRouter : BrowserRouter;
  return (
    <RouterComponent future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
      {children}
    </RouterComponent>
  );
};
