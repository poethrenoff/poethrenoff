document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('comment-form');
    if (!container) return;

    const authorNameKey = container.dataset.storageKey || 'comment_author_name';
    const savedName = localStorage.getItem(authorNameKey);
    const commentUrl = container.dataset.commentUrl;
    const csrfToken = container.dataset.csrfToken;

    function bindToggle(link) {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            const targetId = this.dataset.target;
            const form = document.getElementById('comment-form');
            if (!form) return;

            const isRoot = targetId === 'root';
            const parentId = isRoot ? '' : targetId;

            // If clicking the same button and form is visible, hide it
            const isVisible = form.style.display === 'block';
            if (isVisible && form.dataset.parentId === parentId) {
                form.style.display = 'none';
                return;
            }

            // Move form
            if (isRoot) {
                const topActions = document.querySelector('.comment-actions-top');
                if (topActions) {
                    topActions.after(form);
                } else {
                    const section = document.querySelector('.comments-section');
                    if (section) section.prepend(form);
                }
            } else {
                const commentContainer = document.getElementById('comment-' + targetId);
                if (commentContainer) {
                    const actions = commentContainer.querySelector('.comment-actions');
                    if (actions) actions.after(form);
                }
            }

            form.dataset.parentId = parentId;
            form.style.display = 'block';

            const editor = form.querySelector('.comment-form-editor');
            if (editor) editor.focus();

            const errorDiv = form.querySelector('.comment-form-error');
            if (errorDiv) errorDiv.style.display = 'none';
        });
    }

    function initForm() {
        const editor = container.querySelector('.comment-form-editor');
        const btnSubmit = container.querySelector('.btn-submit');
        const btnCancel = container.querySelector('.btn-cancel');

        // Hotkeys
        editor.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey && !e.ctrlKey) {
                e.preventDefault();
                submitComment(container);
            }
        });

        // Submit
        btnSubmit.addEventListener('click', function () {
            submitComment(container);
        });

        // Cancel
        btnCancel.addEventListener('click', function () {
            container.style.display = 'none';
        });

        // Load saved name
        if (savedName) {
            const authorInput = container.querySelector('.comment-form-name');
            if (authorInput) authorInput.value = savedName;
        }
    }

    function submitComment(container) {
        const parentId = container.dataset.parentId;
        const authorInput = container.querySelector('.comment-form-name');
        const editor = container.querySelector('.comment-form-editor');
        const errorDiv = container.querySelector('.comment-form-error');
        const submitBtn = container.querySelector('.btn-submit');

        const author = authorInput.value.trim();
        const content = editor.innerHTML.trim();

        if (!author) {
            showError(errorDiv, 'Пожалуйста, введите имя');
            authorInput.focus();
            return;
        }

        if (!content || content === '<br>' || editor.textContent.trim() === '') {
            showError(errorDiv, 'Пожалуйста, введите комментарий');
            editor.focus();
            return;
        }

        // Save and sync name
        localStorage.setItem(authorNameKey, author);

        errorDiv.style.display = 'none';
        submitBtn.disabled = true;
        const originalText = submitBtn.innerText;
        submitBtn.innerText = 'Отправка...';

        fetch(commentUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                author: author,
                content: content,
                parentId: parentId || null,
                _token: csrfToken
            })
        })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => {
                        throw err;
                    });
                }
                return response.json();
            })
            .then(data => {
                location.reload();
            })
            .catch(error => {
                showError(errorDiv, error.error || 'Произошла ошибка при отправке');
                submitBtn.disabled = false;
                submitBtn.innerText = originalText;
            });
    }

    function showError(div, msg) {
        div.innerText = msg;
        div.style.display = 'block';
    }

    document.querySelectorAll('.comment-toggle-link').forEach(bindToggle);
    initForm();
});
