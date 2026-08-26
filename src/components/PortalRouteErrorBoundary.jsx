import React from 'react';
import { Button } from '@/components/ui/button';

class PortalRouteErrorBoundary extends React.Component {
  constructor(props) {
    super(props);
    this.state = { error: null };
  }

  static getDerivedStateFromError(error) {
    return { error };
  }

  componentDidCatch(error, info) {
    console.error('AAC member portal route render failed:', error, info);
  }

  render() {
    if (!this.state.error) {
      return this.props.children;
    }

    return (
      <section className="mx-auto max-w-3xl bg-white px-4 py-12 text-stone-900">
        <div className="border-b-2 border-[#b71c1c] pb-5">
          <p className="text-[0.72rem] font-semibold uppercase tracking-[0.24em] text-[#b71c1c]">Member Portal</p>
          <h1 className="mt-3 text-3xl font-bold">We could not load this section.</h1>
          <p className="mt-3 text-sm leading-6 text-stone-600">
            Refresh the page to try again. If this keeps happening, this screen will now leave a browser-console error instead of making the portal blank.
          </p>
        </div>
        <div className="mt-6 flex flex-col gap-3 sm:flex-row">
          <Button
            type="button"
            className="rounded-none bg-[#b71c1c] px-6 text-white hover:bg-[#8f1515]"
            onClick={() => window.location.reload()}
          >
            Reload Page
          </Button>
          <Button
            type="button"
            variant="outline"
            className="rounded-none border-[#b71c1c] px-6 text-[#b71c1c]"
            onClick={() => this.setState({ error: null })}
          >
            Try Again
          </Button>
        </div>
      </section>
    );
  }
}

export default PortalRouteErrorBoundary;
