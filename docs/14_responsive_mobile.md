# レスポンシブ対応 / スマホ対応計画

## 現状の問題点

- サイドバーが常時 220px 固定で表示され、スマホでメインコンテンツが極端に狭くなる
- メディアクエリが一切ない（`app.css` にブレークポイント未設定）
- ヘッダーの検索フォームが幅を取りすぎてスマホでレイアウト崩れが起きる

## 方針

- **ブレークポイント**: `768px` 未満をスマホ扱いとする
- **サイドバー**: スマホではオフキャンバス（画面外）に格納し、ハンバーガーボタンで開閉
- **メインコンテンツ**: スマホでは幅 100% を確保
- **JS**: 外部ファイルは作らず `layout.html.twig` の `{% block scripts %}` に `<script>` タグ直書きで完結させる
- **CSS**: 既存の `app.css` にモバイル用スタイルを追記（既存スタイルは変更しない）

## 変更対象ファイル

| ファイル | 変更内容 |
|---|---|
| `templates/layout.html.twig` | ハンバーガーボタンとオーバーレイ要素を追加 |
| `public/assets/css/app.css` | モバイル用スタイルをファイル末尾に追記 |

---

## 詳細設計

### 1. `templates/layout.html.twig` の変更

#### 1-1. ヘッダーにハンバーガーボタンを追加

```html
<!-- 変更前 -->
<header class="app-header">
  <a href="/" class="app-logo">SQNote</a>
  <form class="search-form" ...>...</form>
  <nav class="header-nav">...</nav>
</header>

<!-- 変更後 -->
<header class="app-header">
  <button class="sidebar-toggle" aria-label="メニューを開く">&#9776;</button>  ← 追加
  <a href="/" class="app-logo">SQNote</a>
  <form class="search-form" ...>...</form>
  <nav class="header-nav">...</nav>
</header>
```

#### 1-2. サイドバーの外側にオーバーレイを追加

```html
<!-- 変更前 -->
<div class="app-body">
  <nav class="sidebar">...</nav>
  <main class="main-content">...</main>
</div>

<!-- 変更後 -->
<div class="app-body">
  <div class="sidebar-overlay"></div>  ← 追加（タップで閉じる用）
  <nav class="sidebar">...</nav>
  <main class="main-content">...</main>
</div>
```

#### 1-3. トグル用 JS を `{% block scripts %}` の直前に追加

```html
<script>
  const toggle = document.querySelector('.sidebar-toggle');
  const sidebar = document.querySelector('.sidebar');
  const overlay = document.querySelector('.sidebar-overlay');

  function openSidebar() {
    sidebar.classList.add('is-open');
    overlay.classList.add('is-visible');
  }
  function closeSidebar() {
    sidebar.classList.remove('is-open');
    overlay.classList.remove('is-visible');
  }

  toggle.addEventListener('click', openSidebar);
  overlay.addEventListener('click', closeSidebar);

  // サイドバー内のリンクをタップしたら閉じる（ページ遷移するため）
  sidebar.querySelectorAll('a').forEach(a => a.addEventListener('click', closeSidebar));
</script>
```

---

### 2. `public/assets/css/app.css` への追記内容

`app.css` の末尾に以下を追記する。既存スタイルは一切変更しない。

```css
/* =============================================
   Responsive / Mobile  (max-width: 767px)
   ============================================= */

/* ハンバーガーボタン: PCでは非表示 */
.sidebar-toggle {
  display: none;
  background: none;
  border: none;
  color: #fff;
  font-size: 22px;
  cursor: pointer;
  padding: 0 4px;
  line-height: 1;
}

/* オーバーレイ: 常時非表示、JS で .is-visible を付与 */
.sidebar-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.4);
  z-index: 90;
}
.sidebar-overlay.is-visible {
  display: block;
}

@media (max-width: 767px) {

  /* ハンバーガーを表示 */
  .sidebar-toggle {
    display: block;
  }

  /* ヘッダーの検索フォームを縮小 */
  .search-form {
    max-width: 160px;
  }

  /* ヘッダーナビ（インポート/エクスポート）を非表示（画面幅が足りない） */
  .header-nav {
    display: none;
  }

  /* サイドバー: オフキャンバスに格納 */
  .sidebar {
    position: fixed;
    top: var(--header-height);
    left: 0;
    bottom: 0;
    width: 260px;
    transform: translateX(-100%);
    transition: transform 0.25s ease;
    z-index: 95;
    box-shadow: 2px 0 8px rgba(0, 0, 0, 0.15);
  }

  /* JS で .is-open を付与するとスライドイン */
  .sidebar.is-open {
    transform: translateX(0);
  }

  /* メインコンテンツを全幅に */
  .main-content {
    width: 100%;
    padding: 16px;
  }

  /* ノート本文の max-width を解除 */
  .note-body {
    max-width: 100%;
  }

  /* ノート詳細ヘッダーのタイトルフォントを縮小 */
  .note-detail-header h1 {
    font-size: 18px;
  }

  /* エディタのメタ行を縦並びに */
  .editor-meta {
    flex-direction: column;
  }

  /* インポート/エクスポートページの幅を解除 */
  .import-page, .export-page {
    max-width: 100%;
  }
}
```

---

## 実装手順

1. `public/assets/css/app.css` の末尾にモバイル用スタイルを追記
2. `templates/layout.html.twig` を編集
   - ハンバーガーボタンをヘッダーの先頭に追加
   - `.sidebar-overlay` div を `.app-body` 内・サイドバーの直前に追加
   - トグル JS を `{% block scripts %}` の直前に追記
3. サーバーへデプロイして確認
   - PC（769px 以上）でサイドバーが通常表示されること
   - スマホ（768px 以下）でサイドバーが非表示になりハンバーガーで開閉できること

## 確認ポイント

- [ ] PC: サイドバーが常時表示、ハンバーガーボタンが非表示
- [ ] スマホ: メインコンテンツが全幅、ハンバーガーでサイドバーが開閉
- [ ] スマホ: サイドバー内リンクをタップするとサイドバーが閉じてページ遷移
- [ ] スマホ: オーバーレイをタップするとサイドバーが閉じる
- [ ] タブレット（768〜1024px）でレイアウトが崩れないこと
