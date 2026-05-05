const renderMessage = (type, msg) => {
    if (Joomla && Joomla.renderMessages && typeof Joomla.renderMessages === 'function') {
        Joomla.renderMessages({[type]: msg});
    } else {
        alert(msg);
    }
};

async function updateReviews(el) {
    el.setAttribute('disabled', '');

    const moduleId = el.getAttribute('data-id');
    const token    = el.getAttribute('data-token') || Joomla.getOptions('csrf.token');

    if (!moduleId || !token) {
        renderMessage('error', ['You first need to fill out the Settings and save the module before attempting to retrieve data from Google!']);
        el.removeAttribute('disabled');
        return;
    }

    const url = prettyReviewsOptions.endpoint;

    const body = new FormData();
    body.append('moduleId', moduleId);
    body.append(token, '1');

    try {
        const response = await fetch(url, {method: 'POST', body});
        const resp = await response.json();

        if (response.ok && resp.success === true && resp.data === true) {
            renderMessage('success', ['Reviews have been updated!']);
        } else {
            console.error(resp);
            renderMessage('error', [resp.message || 'Something went wrong with the Ajax Request!']);
        }
    } catch (err) {
        console.error(err);
        renderMessage('error', ['Something went wrong with the Ajax Request!']);
    } finally {
        el.removeAttribute('disabled');
    }
}
