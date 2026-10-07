/**
 * Send a document to the printer in one click.
 *
 * Opening the PDF in a tab leaves the person in the browser's viewer, where
 * they still have to find the printer icon and then confirm. Fetching it and
 * handing it to a hidden frame instead puts the print dialog up straight away
 * with the document already in it.
 *
 * The dialog itself cannot be skipped from a web page — browsers do not allow
 * it. Chrome started with --kiosk-printing honours this same call without one,
 * which is how a counter machine gets to a genuine single click.
 *
 * The document is fetched rather than pointed at, because a frame loads an
 * error page just as successfully as a PDF: without checking first, a refused
 * or missing document would end up in the print dialog instead of in front of
 * the person who needs to read it.
 */

/** Printing is slow to start; the frame and its blob go once it is safely under way. */
const CLEANUP_DELAY = 60000;

export async function printDocument(url, { onError } = {}) {
    // Whatever goes wrong, the document still has to end up on screen.
    const openInTab = (message) => {
        if (onError) {
            onError(message);
        }
        window.open(url, '_blank');
    };

    let response;

    try {
        response = await fetch(url, { credentials: 'same-origin' });
    } catch (e) {
        openInTab('Could not reach the server to print this. Opening it in a new tab instead.');

        return;
    }

    if (!response.ok) {
        // A refusal or a missing record is something to read, not to print.
        openInTab(null);

        return;
    }

    const type = response.headers.get('content-type') || '';
    if (!type.includes('pdf')) {
        // The server answered with something else — a login page, most likely.
        openInTab(null);

        return;
    }

    let objectUrl;

    try {
        objectUrl = URL.createObjectURL(await response.blob());
    } catch (e) {
        openInTab(null);

        return;
    }

    const frame = document.createElement('iframe');
    frame.setAttribute('aria-hidden', 'true');
    frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden';

    const discard = () => {
        if (frame.parentNode) {
            frame.parentNode.removeChild(frame);
        }
        URL.revokeObjectURL(objectUrl);
    };

    let settled = false;

    frame.onload = () => {
        settled = true;
        try {
            // Chrome needs the viewer inside the frame to take focus first, or
            // the dialog offers the page behind it instead of the document.
            frame.contentWindow.focus();
            frame.contentWindow.print();
            setTimeout(discard, CLEANUP_DELAY);
        } catch (e) {
            discard();
            openInTab('This browser could not open the print dialog. Opening the document in a new tab instead.');
        }
    };

    document.body.appendChild(frame);
    frame.src = objectUrl;

    // A browser with no inline PDF viewer never fires onload, and the click
    // would otherwise appear to have done nothing at all.
    setTimeout(() => {
        if (!settled) {
            discard();
            openInTab('This browser cannot print PDFs directly. Opening the document in a new tab instead.');
        }
    }, 15000);
}

export default printDocument;
