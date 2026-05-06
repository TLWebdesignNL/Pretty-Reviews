const translate = (key, fallback) => {
    if (typeof Joomla !== 'undefined' && Joomla.Text && typeof Joomla.Text._ === 'function') {
        return Joomla.Text._(key, fallback);
    }

    return fallback;
};

const renderMessage = (type, msg) => {
    if (typeof Joomla !== 'undefined' && Joomla.renderMessages && typeof Joomla.renderMessages === 'function') {
        Joomla.renderMessages({[type]: msg});
    } else {
        alert(msg);
    }
};

async function updateReviews(el) {
    el.setAttribute('disabled', '');

    const moduleId = el.getAttribute('data-id');
    const token    = el.getAttribute('data-token')
        || (typeof Joomla !== 'undefined' && typeof Joomla.getOptions === 'function' ? Joomla.getOptions('csrf.token') : '');

    if (!moduleId || !token) {
        renderMessage('error', [translate(
            'MOD_PRETTYREVIEWS_UPDATE_MISSING_MODULE_OR_TOKEN',
            'Save the module before retrieving data from Google.'
        )]);
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
            renderMessage('success', [translate('MOD_PRETTYREVIEWS_UPDATE_SUCCESS', 'Reviews have been updated.')]);
        } else {
            console.error(resp);
            renderMessage('error', [resp.message || translate(
                'MOD_PRETTYREVIEWS_UPDATE_AJAX_ERROR',
                'Something went wrong with the Ajax request.'
            )]);
        }
    } catch (err) {
        console.error(err);
        renderMessage('error', [translate(
            'MOD_PRETTYREVIEWS_UPDATE_AJAX_ERROR',
            'Something went wrong with the Ajax request.'
        )]);
    } finally {
        el.removeAttribute('disabled');
    }
}
