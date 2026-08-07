document.addEventListener('alpine:init', () => {
    Alpine.data('app', () => ({
        username: '',
        password: '',
        generalError: '',
        view: 'feed',
        poems: [],
        audio: [],
        loading: false,
        showNew: false,
        isRecording: false,
        recordingTime: 0,
        recordingInterval: null,
        mediaRecorder: null,
        audioChunks: [],
        audioPlayer: new Audio(),
        isPlayingId: null,
        pausedId: null,
        rewriteId: null,
        recognizingId: null,
        newTitle: '',
        newContent: '',
        newComment: '',
        sortable: null,
        editingId: null,
        editingAudioId: null,
        originalPoem: null,
        originalAudioTitle: '',
        draftKey: 'newPoemDraft',
        stats: { total: 0, trash: 0, streak: 0, max_streak: 0 },
        toc: [],

        async api(url, options = {}) {
            options.headers = options.headers || {};
            if (options.body && !(options.body instanceof FormData) && !options.headers['Content-Type']) {
                options.headers['Content-Type'] = 'application/json';
            }
            if (!options.headers['Accept']) {
                options.headers['Accept'] = 'application/json';
            }

            try {
                const res = await fetch(url, options);
                if (res.status === 401) {
                    window.location.reload();
                    return null;
                }
                return res;
            } catch (e) {
                console.error('API Error:', e);
                return null;
            }
        },

        formatDuration(seconds) {
            if (!seconds) return '0:00';
            const m = Math.floor(seconds / 60);
            const s = Math.floor(seconds % 60);
            return `${m}:${s.toString().padStart(2, '0')}`;
        },

        async init() {
            const hash = location.hash.slice(1);
            if (hash === 'audio' || hash === 'trash') {
                this.view = hash;
            } else if (hash.startsWith('poem-')) {
                this.view = 'feed';
            } else {
                this.view = 'feed';
            }
            await this.load();
            await this.loadStats();

            this.$watch('newTitle', () => this.saveDraft());
            this.$watch('newContent', () => this.saveDraft());
            this.$watch('newComment', () => this.saveDraft());

            if (hash.startsWith('poem-')) {
                const id = parseInt(hash.replace('poem-', ''), 10);
                if (!isNaN(id)) {
                    await this.scrollToPoem(id);
                }
            }

            window.addEventListener('hashchange', () => {
                const h = location.hash.slice(1);
                if (h === 'feed' || h === 'audio' || h === 'trash') {
                    if (this.view !== h) {
                        this.go(h);
                    }
                } else if (h.startsWith('poem-')) {
                    const id = parseInt(h.replace('poem-', ''), 10);
                    if (!isNaN(id)) {
                        this.scrollToPoem(id);
                    }
                }
            });
        },

        async openNew() {
            this.showNew = true;
            try {
                const raw = localStorage.getItem(this.draftKey);
                if (raw) {
                    const draft = JSON.parse(raw);
                    this.newTitle = draft.title || '';
                    this.newContent = draft.content || '';
                    this.newComment = draft.comment || '';

                    await this.$nextTick(() => {
                        const refName = this.view === 'audio' ? 'newFormAudio' : 'newFormFeed';
                        document.querySelector(`[x-ref="${refName}"] .poem-textarea`)?.focus();
                    });
                }
            } catch (e) {
                this.clearDraft();
            }
        },

        async focusNewTextarea() {
            await this.$nextTick();
            const refName = this.view === 'audio' ? 'newFormAudio' : 'newFormFeed';
            document.querySelector(`[x-ref="${refName}"] .poem-textarea`)?.focus();
        },

        async exportPoems() {
            const a = document.createElement('a');
            a.href = '/poems/export/';
            a.download = '';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        },

        async cancelNew() {
            this.showNew = false;
            this.newTitle = '';
            this.newContent = '';
            this.newComment = '';
            await this.clearDraft();
        },

        async clearDraft() {
            localStorage.removeItem(this.draftKey);
        },

        async saveDraft() {
            if (!this.showNew) return;
            if (!this.newTitle && !this.newContent && !this.newComment) {
                await this.clearDraft();
                return;
            }
            localStorage.setItem(this.draftKey, JSON.stringify({
                title: this.newTitle,
                content: this.newContent,
                comment: this.newComment,
            }));
        },

        async go(target) {
            if (this.view === target && location.hash === '#' + target) return;
            this.view = target;
            this.poems = [];
            location.hash = '#' + target;
            await this.load();
            await this.loadStats();
        },

        async extractError(res) {
            if (!res) return 'Ошибка сети';
            try {
                const data = await res.json();
                return data.title || data.error.message || res.statusText || 'Ошибка';
            } catch (e) {
                return res.statusText || 'Ошибка';
            }
        },

        async load() {
            this.loading = true;
            this.generalError = '';

            if (this.view === 'audio') {
                const res = await this.api('/audio/');
                if (res && res.ok) {
                    this.audio = await res.json();
                } else if (res) {
                    this.generalError = await this.extractError(res);
                }
                this.loading = false;
                return;
            }

            const status = this.view === 'trash' ? 'trash' : 'draft';
            const res = await this.api(`/poems/?status=${status}`, {
                headers: {
                    'Accept': 'application/json'
                }
            });
            if (!res) {
                this.loading = false;
                return;
            }
            if (!res.ok) {
                this.poems = [];
                this.generalError = await this.extractError(res);
                this.loading = false;
                return;
            }
            const data = await res.json();
            this.poems = data.results || data;
            if (this.view === 'feed' && data.total !== undefined) {
                this.stats.total = data.total;
            }
            if (this.view === 'feed') {
                this.$nextTick(() => this.initSortable());
            }
            await this.updateToc();
            this.loading = false;
        },

        async loadStats() {
            if (this.view === 'trash') return;
            const res = await this.api('/poems/stats/');
            if (res && res.ok) {
                const data = await res.json();
                this.stats.total = data.total;
                this.stats.trash = data.trash;
                this.stats.streak = data.streak;
                this.stats.max_streak = data.max_streak;
            }
        },

        async validateCommentDate(value) {
            if (!value) return null;
            const match = value.match(/^(\d{2})\.(\d{2})\.(\d{4})$/);
            if (!match) {
                return 'Некорректный формат даты. Используйте дд.мм.гггг';
            }
            const [, d, m, y] = match.map(Number);
            const date = new Date(Date.UTC(y, m - 1, d));
            if (date.getUTCFullYear() !== y || date.getUTCMonth() + 1 !== m || date.getUTCDate() !== d) {
                return 'Некорректная дата';
            }
            return null;
        },

        async updateToc() {
            this.toc = this.poems.map(p => ({ id: p.id, title: p.title }));
        },

        async scrollToPoem(id) {
            let el = document.getElementById('poem-' + id);
            if (!el) {
                this.view = 'feed';
                await this.load();
                await new Promise(r => this.$nextTick(r));
                el = document.getElementById('poem-' + id);
            }

            if (el) {
                const header = document.querySelector('.site-header');
                const offset = header ? header.offsetHeight : 0;
                const top = el.getBoundingClientRect().top + window.scrollY - offset - 14;
                window.scrollTo({ top, behavior: 'smooth' });
            }
        },

        async scrollToTop() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },

        async scrollToBottom() {
            window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
        },

        _sortableMovedEl: null,
        _sortableMovedNext: null,

        async initSortable() {
            if (this.sortable) this.sortable.destroy();
            if (!this.$refs.list) return;
            this.sortable = Sortable.create(this.$refs.list, {
                animation: 150,
                handle: '.drag-handle',
                fallbackOnBody: false,
                onStart: (evt) => {
                    this._sortableMovedEl = evt.item;
                    this._sortableMovedNext = evt.item.nextSibling;
                },
                onEnd: (evt) => this.onReorder(evt),
            });
        },

        async onReorder(evt) {
            const oldIndex = evt.oldIndex;
            const newIndex = evt.newIndex;
            if (newIndex === oldIndex) return;

            const moved = this.poems[oldIndex];
            const newPoems = [...this.poems];
            newPoems.splice(oldIndex, 1);
            newPoems.splice(newIndex, 0, moved);
            this.poems = newPoems;

            const beforeNeighbor = newPoems[newIndex + 1] || null;
            const afterNeighbor = newPoems[newIndex - 1] || null;

            const payload = {
                _token: CSRF_TOKENS.work_poem_reorder,
                before_id: beforeNeighbor && beforeNeighbor.id !== moved.id ? beforeNeighbor.id : null,
                after_id: afterNeighbor && afterNeighbor.id !== moved.id ? afterNeighbor.id : null,
            };

            const res = await this.api(`/poems/${moved.id}/reorder/`, {
                method: 'POST',
                body: JSON.stringify(payload),
            });

            if (!res || !res.ok) {
                await this.load();
                if (res) {
                    this.generalError = await this.extractError(res);
                }
            } else {
                await this.updateToc();
            }
        },

        async create() {
            if (!this.newContent.trim() && !this.newTitle.trim()) return;

            const dateError = await this.validateCommentDate(this.newComment);
            if (dateError) {
                this.generalError = dateError;
                return;
            }

            this.generalError = '';
            const res = await this.api('/poems/', {
                method: 'POST',
                body: JSON.stringify({
                    _token: CSRF_TOKENS.work_poem_create,
                    title: this.newTitle || null,
                    content: this.newContent,
                    comment: this.newComment || null,
                })
            });
            if (res && res.ok) {
                this.newTitle = '';
                this.newContent = '';
                this.newComment = '';
                this.showNew = false;
                await this.clearDraft();
                await this.load();
                await this.updateToc();
                await this.loadStats();
            } else if (res) {
                this.generalError = await this.extractError(res);
            }
        },

        async edit(poem) {
            this.originalPoem = { ...poem };
            this.editingId = poem.id;
            poem.comment = poem.comment || '';
            await this.$nextTick(() => {
                const el = document.querySelector(`.editing .poem-textarea`);
                if (el) el.focus();
            });
        },

        async copyPoem(poem) {
            const title = poem.title || '* * *';
            const content = poem.content || '';
            const comment = poem.comment || '';
            const parts = [title, content];
            if (comment) parts.push(comment);
            const text = parts.join('\n\n');
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            const successful = document.execCommand('copy');
            document.body.removeChild(ta);
            if (successful) {
                const btn = document.querySelector(`[data-copy-id="${poem.id}"]`);
                if (btn) {
                    btn.classList.add('copied');
                    setTimeout(() => btn.classList.remove('copied'), 1500);
                }
            }
        },

        async cancelEdit() {
            this.editingId = null;
            this.originalPoem = null;
        },

        async saveEdit(poem) {
            this.generalError = '';
            const dateError = await this.validateCommentDate(poem.comment);
            if (dateError) {
                this.generalError = dateError;
                return;
            }

            const res = await this.api(`/poems/${poem.id}/`, {
                method: 'PUT',
                body: JSON.stringify({
                    _token: CSRF_TOKENS.work_poem_update,
                    title: poem.title || null,
                    content: poem.content,
                    comment: poem.comment || null,
                }),
            });
            if (res && res.ok) {
                const updated = await res.json();
                Object.assign(poem, updated);
                await this.updateToc();
                await this.loadStats();
                this.editingId = null;
                this.originalPoem = null;
            } else if (res) {
                this.generalError = await this.extractError(res);
            }
        },

        async trash(poem) {
            this.generalError = '';
            const res = await this.api(`/poems/${poem.id}/trash/`, {
                method: 'POST',
                body: JSON.stringify({ _token: CSRF_TOKENS.work_poem_trash }),
            });
            if (res && res.ok) {
                await this.load();
                await this.updateToc();
                await this.loadStats();
            } else if (res) {
                this.generalError = await this.extractError(res);
            }
        },

        async restore(poem) {
            this.generalError = '';
            const res = await this.api(`/poems/${poem.id}/restore/`, {
                method: 'POST',
                body: JSON.stringify({ _token: CSRF_TOKENS.work_poem_restore }),
            });
            if (res && res.ok) {
                await this.load();
                await this.updateToc();
                await this.loadStats();
            } else if (res) {
                this.generalError = await this.extractError(res);
            }
        },

        async remove(poem) {
            if (!confirm('Удалить навсегда? Это действие необратимо.')) return;
            this.generalError = '';
            const res = await this.api(`/poems/${poem.id}/`, {
                method: 'DELETE',
                body: JSON.stringify({ _token: CSRF_TOKENS.work_poem_delete }),
            });
            if (res && res.ok) {
                await this.load();
                await this.updateToc();
                await this.loadStats();
            } else if (res) {
                this.generalError = await this.extractError(res);
            }
        },

        async togglePlay(item) {
            if (this.isPlayingId === item.id) {
                this.audioPlayer.pause();
                this.pausedId = item.id;
                this.isPlayingId = null;
            } else if (this.pausedId === item.id) {
                this.isPlayingId = item.id;
                this.pausedId = null;
                this.audioPlayer.play();
            } else {
                if (this.isPlayingId) {
                    this.audioPlayer.pause();
                }
                this.audioPlayer.src = `/audio/${item.id}`;
                this.audioPlayer.play();
                this.isPlayingId = item.id;
                this.pausedId = null;
                this.audioPlayer.onended = () => {
                    this.isPlayingId = null;
                    this.pausedId = null;
                };
            }
        },

        async stopPlayback(item) {
            if (this.isPlayingId === item.id) {
                this.audioPlayer.pause();
                this.audioPlayer.currentTime = 0;
                this.isPlayingId = null;
                this.pausedId = null;
            }
        },

        async editAudio(item) {
            this.originalAudioTitle = item.title;
            this.editingAudioId = item.id;
            await this.$nextTick(() => {
                const el = document.getElementById('audio-input-' + item.id);
                if (el) el.focus();
            });
        },

        async cancelAudioRename(item) {
            item.title = this.originalAudioTitle;
            this.editingAudioId = null;
        },

        async saveAudioRename(item) {
            if (!this.editingAudioId) return;
            if (item.title === this.originalAudioTitle) {
                this.editingAudioId = null;
                return;
            }
            if (!item.title.trim()) {
                this.cancelAudioRename(item);
                return;
            }
            const res = await this.api(`/audio/${item.id}/rename`, {
                method: 'POST',
                body: JSON.stringify({ _token: CSRF_TOKENS.work_audio_rename, title: item.title })
            });
            if (res && res.ok) {
                this.editingAudioId = null;
            } else if (res) {
                this.generalError = await this.extractError(res);
                this.cancelAudioRename(item);
            }
        },

        async deleteAudio(item) {
            if (!confirm('Удалить запись?')) return;
            const res = await this.api(`/audio/${item.id}/delete`, {
                method: 'DELETE',
                body: JSON.stringify({ _token: CSRF_TOKENS.work_audio_delete }),
            });
            if (res && res.ok) {
                await this.load();
            } else if (res) {
                this.generalError = await this.extractError(res);
            }
        },

        async startRecording() {
            try {
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    throw new Error('Ваш браузер не поддерживает запись аудио в небезопасном контексте (требуется HTTPS или localhost).');
                }
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                this.recordingStream = stream;
                this.mediaRecorder = new MediaRecorder(stream);
                this.audioChunks = [];
                this.recordingTime = 0;

                this.mediaRecorder.ondataavailable = (e) => {
                    if (e.data.size > 0) this.audioChunks.push(e.data);
                };

                this.mediaRecorder.onstop = async () => {
                    if (this._cancelRecording) {
                        this._cancelRecording = false;
                        if (this.recordingStream) {
                            this.recordingStream.getTracks().forEach(track => track.stop());
                        }
                        return;
                    }
                    const audioBlob = new Blob(this.audioChunks, { type: 'audio/webm' });
                    await this.saveRecording(audioBlob, this.recordingTime);
                    if (this.recordingStream) {
                        this.recordingStream.getTracks().forEach(track => track.stop());
                    }
                };

                this.mediaRecorder.start();
                this.isRecording = true;
                this.recordingInterval = setInterval(() => {
                    this.recordingTime++;
                }, 1000);
            } catch (err) {
                console.error('Ошибка доступа к микрофону:', err);
                this.generalError = 'Ошибка доступа к микрофону. Убедитесь, что сайт использует HTTPS и доступ разрешен.';
            }
        },

        async stopRecording() {
            if (this.mediaRecorder && this.isRecording) {
                this.mediaRecorder.stop();
                this.isRecording = false;
                clearInterval(this.recordingInterval);
            }
        },

        async cancelRecording() {
            if (this.mediaRecorder && this.isRecording) {
                this._cancelRecording = true;
                this.mediaRecorder.stop();
                this.isRecording = false;
                clearInterval(this.recordingInterval);
                this.audioChunks = [];
                this.recordingTime = 0;
                this.rewriteId = null;
            }
        },

        async saveRecording(blob, duration) {
            let title = '';
            if (this.rewriteId) {
                const item = this.audio.find(a => a.id === this.rewriteId);
                title = item ? item.title : 'Запись';
            } else {
                title = prompt('Введите название записи:', 'Запись от ' + new Date().toLocaleString('ru-RU'));
                if (title === null) {
                    this.rewriteId = null;
                    return;
                }
                if (!title.trim()) title = 'Новая запись';
            }

            const formData = new FormData();
            formData.append('audio', blob, 'recording.webm');
            formData.append('title', title);
            formData.append('duration', duration);
            formData.append('_token', this.rewriteId ? CSRF_TOKENS.work_audio_rewrite : CSRF_TOKENS.work_audio_create);

            const url = this.rewriteId ? `/audio/${this.rewriteId}/rewrite` : '/audio/';
            const res = await this.api(url, {
                method: 'POST',
                body: formData
            });

            if (res && res.ok) {
                await this.load();
                this.rewriteId = null;
            } else if (res) {
                this.generalError = await this.extractError(res);
                this.rewriteId = null;
            }
        },

        async startRewriting(item) {
            if (!confirm('Перезаписать эту запись? Текущий файл будет удален.')) return;
            this.rewriteId = item.id;
            await this.startRecording();
        },

        async recognizeAudio(item) {
            if (this.recognizingId !== null) return;
            this.recognizingId = item.id;
            this.generalError = '';

            try {
                const res = await this.api(`/audio/${item.id}/recognize/`, {
                    method: 'POST',
                    body: JSON.stringify({ _token: CSRF_TOKENS.work_audio_recognize }),
                });

                if (res && res.ok) {
                    const data = await res.json();
                    this.newContent = data.text || '';
                    this.newTitle = '';
                    this.newComment = '';
                    this.showNew = true;

                    await this.$nextTick(() => {
                        const refName = this.view === 'audio' ? 'newFormAudio' : 'newFormFeed';
                        document.querySelector(`[x-ref="${refName}"] .poem-textarea`)?.focus();
                    });
                } else if (res) {
                    this.generalError = await this.extractError(res);
                }
            } catch (e) {
                this.generalError = 'Ошибка при распознавании';
            } finally {
                this.recognizingId = null;
            }
        },
    }));
});
