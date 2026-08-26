
import React from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { X, Copy, ExternalLink } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { toast } from '@/components/ui/use-toast';
import { openExternalUrl } from '@/lib/mobileNavigation';

const DiscountModal = ({ partner, onClose }) => {
  const handleCopyCode = () => {
    if (partner?.code) {
      navigator.clipboard.writeText(partner.code);
      toast({
        title: "✅ Code Copied!",
        description: `Discount code ${partner.code} copied to clipboard!`,
      });
    }
  };

  const handleVisitWebsite = async () => {
    if (partner?.url) {
      await openExternalUrl(partner.url);
    }
  };

  return (
    <AnimatePresence>
      {partner && (
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          className="fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4"
          onClick={onClose}
        >
          <motion.div
            initial={{ scale: 0.9, opacity: 0 }}
            animate={{ scale: 1, opacity: 1 }}
            exit={{ scale: 0.9, opacity: 0 }}
            onClick={(e) => e.stopPropagation()}
            className="card-gradient rounded-2xl p-8 max-w-lg w-full border border-stone-200 shadow-2xl relative"
          >
            <button
              onClick={onClose}
              className="absolute top-4 right-4 text-black/50 hover:text-black transition-colors"
            >
              <X className="w-6 h-6" />
            </button>

            <div className="mb-6">
              <img
                src={partner.logo_url}
                alt={partner.name}
                className="w-full h-48 object-cover rounded-lg mb-4"
              />
              <h3 className="text-3xl font-bold text-black mb-2">{partner.name}</h3>
              <p className="text-black/75">{partner.description}</p>
            </div>

            <div className="bg-[#0B0B0B] rounded-lg p-6 mb-6">
              <div className="flex items-center justify-between mb-4">
                <span className="text-[#E0E0E0]">Discount</span>
                <span className="text-[#B71C1C] text-3xl font-bold">{partner.discount} OFF</span>
              </div>

              <div className="bg-[#2E2E2E] rounded-lg p-4 flex items-center justify-between">
                <div>
                  <p className="text-[#999999] text-sm mb-1">Discount Code</p>
                  <p className="text-white text-2xl font-mono font-bold">{partner.code}</p>
                </div>
                <Button
                  onClick={handleCopyCode}
                  className="bg-[#B71C1C] hover:bg-[#D32F2F] text-white"
                >
                  <Copy className="w-4 h-4 mr-2" />
                  Copy
                </Button>
              </div>
            </div>

            <div className="bg-white p-4 rounded-lg mb-6 flex items-center justify-center">
              <img alt={`${partner.name} QR Code`} className="w-40 h-40" src="https://images.unsplash.com/photo-1626682561113-d1db402cc866" />
            </div>

            <Button
              onClick={handleVisitWebsite}
              className="w-full bg-[#B71C1C] hover:bg-[#D32F2F] text-white h-12 text-lg"
            >
              <ExternalLink className="w-5 h-5 mr-2" />
              Visit Website
            </Button>
          </motion.div>
        </motion.div>
      )}
    </AnimatePresence>
  );
};

export default DiscountModal;
  
