import React, { useEffect } from 'react';
import { useOutletContext, useLocation } from 'react-router-dom';
import { useAuth } from '@/hooks/useAuth';
import ProfileTab from '@/components/tabs/ProfileTab';
import DiscountsTab from '@/components/tabs/DiscountsTab';
import AccountTab from '@/components/tabs/AccountTab';

const MemberPortal = ({ portalTab }) => {
  const { user, profile, loading } = useAuth();
  const { activeTab, setActiveTab } = useOutletContext();
  const location = useLocation();

  useEffect(() => {
    const tabFromUrl = location.pathname.substring(1);
    if (['discounts', 'account'].includes(tabFromUrl)) {
      setActiveTab(tabFromUrl);
    } else if (portalTab) {
      setActiveTab(portalTab);
    } else if (location.pathname === '/') {
      setActiveTab('profile');
    }
  }, [location.pathname, portalTab, setActiveTab]);

  const renderActiveTab = () => {
    if (loading || !profile) {
      return <div className="text-stone-800 text-center pt-10">Loading profile...</div>;
    }
    switch (activeTab) {
      case 'profile':
        return <ProfileTab profile={profile} />;
      case 'discounts':
        return <DiscountsTab profile={profile} />;
      case 'account':
        return <AccountTab profile={profile} />;
      default:
        return <ProfileTab profile={profile} />;
    }
  };

  return (
    <div>
      {renderActiveTab()}
    </div>
  );
};

export default MemberPortal;
