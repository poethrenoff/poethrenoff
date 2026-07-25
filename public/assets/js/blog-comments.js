document.addEventListener('DOMContentLoaded', function() {
    const authorNameKey = 'blog_comment_author_name';
    const savedName = localStorage.getItem(authorNameKey);

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function bindToggle(link) {
        link.addEventListener('click', function(e) {
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
                topActions.after(form);
            } else {
                const commentContainer = document.getElementById('comment-' + targetId);
                const actions = commentContainer.querySelector('.comment-actions');
                actions.after(form);
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
        const container = document.getElementById('comment-form');
        if (!container) return;

        const editor = container.querySelector('.comment-form-editor');
        const btnSubmit = container.querySelector('.btn-submit');
        const btnCancel = container.querySelector('.btn-cancel');

        // Hotkeys
        editor.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey && !e.ctrlKey) {
                e.preventDefault();
                submitComment(container);
            }
        });

        // Submit
        btnSubmit.addEventListener('click', function() {
            submitComment(container);
        });

        // Cancel
        btnCancel.addEventListener('click', function() {
            container.style.display = 'none';
        });
    }

    function submitComment(container) {
        const postId = container.dataset.postId;
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
        document.querySelectorAll('.comment-form-name').forEach(input => {
            input.value = author;
        });

        errorDiv.style.display = 'none';
        submitBtn.disabled = true;
        const originalText = submitBtn.innerText;
        submitBtn.innerText = 'Отправка...';

        fetch(`/post/${postId}/comment`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                author: author,
                content: content,
                parentId: parentId || null
            })
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(err => { throw err; });
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

    // Initialize
    document.querySelectorAll('.comment-form-name').forEach(input => {
        if (savedName) input.value = savedName;
    });

    document.querySelectorAll('.comment-toggle-link').forEach(bindToggle);
    initForm();
});
