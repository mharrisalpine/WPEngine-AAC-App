import React from 'react';
import { Download, FileText } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  downloadMembershipConfirmationLetter,
  getMembershipConfirmationLetterBlocks,
} from '@/lib/membershipConfirmationLetter';
import { getFullName } from '@/lib/memberProfile';
import { getPortalUiSettings } from '@/lib/portalSettings';

const AAC_LOGO_URL = 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/09/light-header-logo.svg';

const renderSegments = (segments = []) =>
  segments.map((segment, index) => {
    const text = String(segment?.text || '');
    if (!text) {
      return null;
    }

    return segment.bold ? (
      <strong key={`${text}-${index}`} className="font-semibold text-stone-950">
        {text}
      </strong>
    ) : (
      <React.Fragment key={`${text}-${index}`}>{text}</React.Fragment>
    );
  });

export function ConfirmationLetterPreview({ profile, framed = false, fullWidth = false }) {
  const blocks = React.useMemo(() => getMembershipConfirmationLetterBlocks(profile), [profile]);
  const format = getPortalUiSettings().content?.confirmation_letter_format || 'standard';
  const widthClass = fullWidth
    ? 'w-full max-w-none'
    : format === 'compact'
      ? 'max-w-[640px]'
      : 'max-w-[720px]';

  return (
    <article
      className={`mx-auto bg-white ${
        framed ? 'border border-stone-200 shadow-sm' : ''
      } ${widthClass}`}
    >
      <div className="bg-[#120604] px-5 py-4 sm:px-8">
        <img src={AAC_LOGO_URL} alt="American Alpine Club" className="h-10 w-auto max-w-full" />
      </div>

      <div className={`space-y-1 px-5 py-6 text-[0.95rem] text-stone-800 sm:px-8 sm:text-base ${
        format === 'compact' ? 'leading-7 sm:py-6 sm:leading-7' : 'leading-7 sm:py-8 sm:leading-8'
      }`}>
        {blocks.map((block, index) => {
          if (block.type === 'spacer') {
            const heightClass = block.height >= 8 ? 'h-3' : block.height >= 4 ? 'h-2' : 'h-1';
            return <div key={`spacer-${index}`} aria-hidden className={heightClass} />;
          }

          return (
            <p key={`line-${index}`} className="min-h-[1.5rem]">
              {renderSegments(block.segments)}
            </p>
          );
        })}
      </div>
    </article>
  );
}

export function ConfirmationLetterDialog({ open, onOpenChange, profile }) {
  const memberName = getFullName(profile?.account_info || {});

  const handleDownload = () => {
    void downloadMembershipConfirmationLetter(profile);
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="grid max-h-[calc(100svh-1rem)] w-[calc(100%-1rem)] max-w-3xl grid-rows-[auto,minmax(0,1fr),auto] gap-0 overflow-hidden rounded-none border border-stone-200 bg-white p-0 sm:max-h-[min(92vh,900px)]">
        <DialogHeader className="border-b-2 border-[#b71c1c] px-4 py-4 pr-12 text-left sm:px-6">
          <div className="flex items-center gap-3 text-[#b71c1c]">
            <FileText className="h-5 w-5" />
            <DialogTitle className="text-xl font-bold text-stone-950">
              Confirmation Letter
            </DialogTitle>
          </div>
          <DialogDescription className="text-sm leading-6 text-stone-600">
            Browser preview for {memberName || 'this member'}.
          </DialogDescription>
        </DialogHeader>

        <div className="min-h-0 overflow-y-auto bg-stone-100 p-3 sm:p-6">
          <ConfirmationLetterPreview profile={profile} framed />
        </div>

        <div className="flex flex-col gap-2 border-t border-stone-200 bg-white px-4 py-3 sm:flex-row sm:justify-end sm:px-6">
          <Button
            type="button"
            variant="outline"
            onClick={() => onOpenChange(false)}
            className="min-h-[2.75rem] rounded-none border-stone-300 text-black hover:bg-stone-100"
          >
            Close
          </Button>
          <Button
            type="button"
            onClick={handleDownload}
            className="min-h-[2.75rem] rounded-none bg-[#b71c1c] text-white hover:bg-[#8f1515]"
          >
            <Download className="mr-2 h-4 w-4" />
            Download PDF
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  );
}

export default ConfirmationLetterDialog;
