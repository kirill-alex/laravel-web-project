<template>
    <aside class="article-notifications" aria-label="Новые статьи" aria-live="polite">
        <section
            v-for="article in articles"
            :key="article.id"
            class="article-notification"
            role="status"
        >
            <button
                class="article-notification__close"
                type="button"
                aria-label="Закрыть уведомление"
                @click="dismiss(article.id)"
            >×</button>
            <p class="article-notification__label">Новая статья</p>
            <a :href="article.url" class="article-notification__title">{{ article.title }}</a>
            <p class="article-notification__hint">Нажмите на название, чтобы прочитать</p>
        </section>
    </aside>
</template>

<script>
export default {
    data() {
        return { articles: [] };
    },
    created() {
        if (!window.Echo) {
            return;
        }

        window.Echo.channel('articles').listen('NewArticleEvent', (data) => {
            const article = data.article;

            if (!article || !Number.isInteger(article.id) || typeof article.title !== 'string') {
                return;
            }

            // Build an internal link from the ID rather than trusting an arbitrary URL.
            const notification = { ...article, url: `/articles/${article.id}` };
            this.articles = [notification, ...this.articles.filter((item) => item.id !== article.id)].slice(0, 3);
        });
    },
    beforeUnmount() {
        window.Echo?.leave('articles');
    },
    methods: {
        dismiss(id) {
            this.articles = this.articles.filter((article) => article.id !== id);
        },
    },
};
</script>

<style scoped>
.article-notifications {
    position: fixed;
    right: 20px;
    bottom: 20px;
    z-index: 1000;
    display: grid;
    gap: 12px;
    width: min(360px, calc(100vw - 40px));
    pointer-events: none;
}

.article-notification {
    position: relative;
    padding: 18px 44px 18px 20px;
    border: 1px solid #ddccb5;
    border-left: 4px solid #a67c52;
    border-radius: 10px;
    background: #fffaf3;
    box-shadow: 0 8px 30px rgb(74 63 53 / 18%);
    pointer-events: auto;
}

.article-notification__label {
    margin: 0 0 6px;
    color: #7a6a5a;
    font-size: 13px;
}

.article-notification__title {
    color: #4a3f35;
    font-weight: 600;
    overflow-wrap: anywhere;
}

.article-notification__hint {
    margin: 8px 0 0;
    color: #7a6a5a;
    font-size: 12px;
}

.article-notification__close {
    position: absolute;
    top: 8px;
    right: 8px;
    width: 30px;
    height: 30px;
    padding: 0;
    background: transparent;
    color: #7a6a5a;
    font-size: 24px;
    line-height: 1;
}

.article-notification__close:hover {
    background: #ede0cc;
}
</style>
