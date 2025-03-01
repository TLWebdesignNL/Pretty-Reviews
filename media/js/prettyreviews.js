const renderMessage = (type, msg) => {
    if (Joomla && Joomla.renderMessages && typeof Joomla.renderMessages === 'function') {
        Joomla.renderMessages({[type]: msg});
    } else {
        alert(msg);
    }
}

document.addEventListener("DOMContentLoaded", function () {
    let secretField = document.getElementById("jform_params_secret");

    if (secretField && secretField.value.trim() === "") {
        secretField.value = crypto.randomUUID(); // Generate and set GUID
    }
});
async function updateReviews(el) {
    el.setAttribute('disabled', '');
    let dataId  = el.getAttribute('data-id');
    let dataCid = el.getAttribute('data-cid');
    let dataReviewSort = el.getAttribute('data-reviewsort');
    let dataApiKey = el.getAttribute('data-apiKey');
    let dataSecret = el.getAttribute('data-secret');
    let tokenElement = document.querySelector('input[type="hidden"][value="1"]');
    let token = tokenElement.getAttribute('name');

    let JoomlaRoot = prettyReviewsOptions.baseUrl;
    const url = new URL(JoomlaRoot + 'index.php?option=com_ajax&module=prettyreviews&method=updateGoogleReviews&format=json&moduleId=' + dataId + '&cid=' + dataCid + '&apiKey=' + dataApiKey + '&reviewSort=' + dataReviewSort + '&secret=' + dataSecret );

    if (dataCid && dataReviewSort && dataApiKey && dataSecret) {
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
