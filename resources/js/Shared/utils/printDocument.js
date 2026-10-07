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
 * The document is fetched first, because a frame loads an error page just as
 * successfully as a PDF: without checking, a refused or missing document would
 * end up in the print dialog instead of in front of the person who needs to
 * read it. Only once it is known to be a PDF is the frame pointed at it.
 */

/** Printing is slow to start; the frame and its blob go once it is safely under way. */
const CLEANUP_DELAY = 60000;

/** Long enough for the PDF viewer to paint the first page before it is captured. */
const RENDER_DELAY = 400;

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

    // Out of sight, but laid out at full size: Chrome's PDF viewer paints
    // nothing in a frame that is hidden or has no dimensions, and the print
    // dialog then captures a blank sheet. Moving it off-screen keeps it
    // rendering while keeping it out of the way.
    const frame = document.createElement('iframe');
    frame.setAttribute('aria-hidden', 'true');
    frame.setAttribute('tabindex', '-1');
    frame.style.cssText = 'position:fixed;left:-10000px;top:0;width:216mm;height:330mm;border:0';

    const discard = () => {
        if (frame.parentNode) {
            frame.parentNode.removeChild(frame);
        }
    };

    let settled = false;

    frame.onload = () => {
        settled = true;
        // onload fires when the document arrives, not when the viewer has
        // finished drawing it; printing on the same tick catches it empty.
        setTimeout(() => {
            try {
                // Chrome needs the viewer inside the frame to take focus first,
                // or the dialog offers the page behind it instead.
                frame.contentWindow.focus();
                frame.contentWindow.print();
                setTimeout(discard, CLEANUP_DELAY);
            } catch (e) {
                discard();
                openInTab('This browser could not open the print dialog. Opening the document in a new tab instead.');
            }
        }, RENDER_DELAY);
    };

    document.body.appendChild(frame);
    // The address itself, not a blob of it: this is the same path the browser
    // takes when the document is opened in a tab, which is known to render
    // here. The check above has already established it is a PDF, so the second
    // request costs a re-render and buys a viewer that behaves.
    frame.src = url;

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
