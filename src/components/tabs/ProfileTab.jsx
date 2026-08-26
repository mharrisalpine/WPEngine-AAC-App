
import React from 'react';
import { motion } from 'framer-motion';
import MembershipCard from '@/components/MembershipCard';
import LineArchiveReader from '@/components/LineArchiveReader';

const ProfileTab = ({ profile }) => {
  if (!profile) return <div className="text-stone-800">Loading profile...</div>;

  return (
    <div className="py-6">
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.5 }}
        className="space-y-6"
      >
        <MembershipCard profile={profile} />
        <LineArchiveReader compactOnly />
      </motion.div>
    </div>
  );
};

export default ProfileTab;
