const renderMessage = (type, msg) => {
    if (Joomla && Joomla.renderMessages && typeof Joomla.renderMessages === 'function') {
        Joomla.renderMessages({[type]: msg});
    } else {
        alert(msg);
    }
}
async function updateReviews(el) {
    el.setAttribute('disabled', '');
    let dataId  = el.getAttribute('data-id');
    let dataCid = el.getAttribute('data-cid');
    let dataReviewSort = el.getAttribute('data-reviewsort');
    let dataApiKey = el.getAttribute('data-apiKey');
    let JoomlaRoot = prettyReviewsOptions.baseUrl;
    const url = new URL(JoomlaRoot + 'index.php?option=com_ajax&module=prettyreviews&method=updateGoogleReviews&format=json&moduleId=' + dataId + '&cid=' + dataCid + '&apiKey=' + dataApiKey + '&reviewSort=' + dataReviewSort);

    if (dataCid && dataReviewSort && dataApiKey) {
        try {
            const response = await fetch(url, {method: 'GET'});

            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const resp = await response.json();

            if (resp.data === true) {
                renderMessage('success', ['Reviews have been updated!']);
            } else {
                console.error(resp);
                renderMessage('error', ['Something went wrong with the Ajax Request!']);
            }
        } catch (err) {
            console.error(err);
            renderMessage('error', ['Something went wrong with the Ajax Request!']);
        } finally {
            el.removeAttribute('disabled');
        }
    } else {
        renderMessage('error', ['You first need to fill out the Settings and save the module before attempting to retrieve data from Google! ']);
    }
}
