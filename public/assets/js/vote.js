document.querySelectorAll('.vote-btn').forEach(button => {
    button.addEventListener('click', function() {
        const container = this.closest('.work-stats');
        const workId = container.dataset.workId;
        const token = container.dataset.token;
        const type = this.dataset.type;

        const formData = new FormData();
        formData.append('_token', token);
        formData.append('type', type);

        fetch(`/work/vote/${workId}`, {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (!data.error) {
                    container.querySelector('.vote-btn[data-type="like"] .count').textContent = data.likes;
                    container.querySelector('.vote-btn[data-type="dislike"] .count').textContent = data.dislikes;
                }
            });
    });
});
