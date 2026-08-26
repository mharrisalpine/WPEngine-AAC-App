import React, { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { X, Image as ImageIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const ChangePhotoModal = ({ isOpen, onClose, onSave }) => {
  const [newPhotoUrl, setNewPhotoUrl] = useState('');

  const handleSave = () => {
    onSave(newPhotoUrl);
    onClose();
  };

  return (
    <AnimatePresence>
      {isOpen && (
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          className="fixed inset-0 bg-black/80 z-[100] flex items-center justify-center p-4"
          onClick={onClose}
        >
          <motion.div
            initial={{ scale: 0.9, opacity: 0 }}
            animate={{ scale: 1, opacity: 1 }}
            exit={{ scale: 0.9, opacity: 0 }}
            onClick={(e) => e.stopPropagation()}
            className="card-gradient rounded-2xl p-8 max-w-md w-full border border-stone-200 shadow-2xl relative"
          >
            <button
              onClick={onClose}
              className="absolute top-4 right-4 text-black/50 hover:text-black transition-colors"
            >
              <X className="w-6 h-6" />
            </button>
            <h3 className="text-2xl font-bold text-black mb-6">Change Profile Photo</h3>
            
            <div className="space-y-4">
              <div className="flex items-center justify-center">
                  <div className="w-32 h-32 rounded-full bg-stone-100 border-4 border-stone-200 flex items-center justify-center">
                    {newPhotoUrl ? (
                      <img src={newPhotoUrl} alt="New profile" className="w-full h-full object-cover rounded-full" />
                    ) : (
                      <ImageIcon className="w-16 h-16 text-black/35" />
                    )}
                  </div>
              </div>

              <div>
                <Label htmlFor="photo-url" className="text-black">Image URL</Label>
                <Input
                  id="photo-url"
                  value={newPhotoUrl}
                  onChange={(e) => setNewPhotoUrl(e.target.value)}
                  placeholder="https://images.unsplash.com/..."
                  className="bg-white border-[#d9d9d9] text-black mt-1"
                />
              </div>

              <p className="text-xs text-black/60">
                Since file uploads are not available in this environment, please paste an image URL to update your photo.
              </p>
            </div>
            
            <div className="mt-8 flex justify-end gap-3">
              <Button variant="outline" onClick={onClose}>Cancel</Button>
              <Button onClick={handleSave} className="bg-[#b71c1c] hover:bg-[#8f1515] text-white">Save Photo</Button>
            </div>
          </motion.div>
        </motion.div>
      )}
    </AnimatePresence>
  );
};

export default ChangePhotoModal;
