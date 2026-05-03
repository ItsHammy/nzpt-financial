//IMPORTED FROM URGENCY VIEWER - NOT CURRENTLY IMPLEMENTED AS PART OF FINANCIAL DASHBOARD (but will be)
// Share button functionality
document.addEventListener('DOMContentLoaded', () => {

  document.getElementById('share-btn').addEventListener('click', async () => {
    if (navigator.share) {
      try {
        // Fetch the image and convert to a File object
        const response = await fetch('https://nzpt.cjs.nz/assets/nzptshare.png');
        const blob = await response.blob();
        const file = new File([blob], 'nzptshare.png', { type: 'image/png' });

        // Check the browser can share files before trying
        if (navigator.canShare && navigator.canShare({ files: [file] })) {
          await navigator.share({
            title: 'NZPolToolbox – Urgency Tracker',
            text: 'Check out the latest NZ Parliament urgency statistics. #nzpol',
            url: 'https://nzpt.cjs.nz',
            files: [file]
          });
        } else {
          // Browser supports sharing but not files — share without image
          await navigator.share({
            title: 'NZPolToolbox – Urgency Tracker',
            text: 'Check out the latest NZ Parliament urgency statistics. #nzpol via NZPT',
            url: 'https://nzpt.cjs.nz'
          });
        }
      } catch (err) {
        if (err.name !== 'AbortError') {
          // AbortError just means the user cancelled — ignore it
          console.error('Share failed:', err);
        }
      }
    } else {
      // Fallback for Firefox desktop etc.
      await navigator.clipboard.writeText('https://nzpt.cjs.nz');
      alert('Link copied to clipboard!');
    }
  });
});