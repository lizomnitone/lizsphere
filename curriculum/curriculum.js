// ============================================================
// PERSONAL CURRICULUM — notebooks, subjects, colours
// Each subject's page content lives in curriculum/<notebook>/<slug>.md
// `tab` is the short label on the notebook tab (defaults to `name`).
// ============================================================
const NOTEBOOKS = {
  humanities: {
    title: 'Humanities',
    cover: { base: '#f5a538', left: '#f9c6ef', right: '#ffc83a' },
    subjects: [
      { slug: 'history', name: 'History' },
      { slug: 'literature', name: 'Literature' },
      { slug: 'philosophy', name: 'Philosophy' },
      { slug: 'languages', name: 'Languages' },
      { slug: 'art-history', name: 'Art History' },
      { slug: 'geography', name: 'Geography' },
    ],
  },
  sciences: {
    title: 'Sciences',
    cover: { base: '#2bbf8a', left: '#29d4ff', right: '#ffe08a' },
    subjects: [
      { slug: 'biology', name: 'Biology' },
      { slug: 'chemistry', name: 'Chemistry' },
      { slug: 'physics', name: 'Physics' },
      { slug: 'ecology', name: 'Ecology' },
      { slug: 'astronomy', name: 'Astronomy' },
      { slug: 'math', name: 'Math' },
    ],
  },
  practical: {
    title: 'Practical & Creative Studies',
    cover: { base: '#e3548e', left: '#e86cf0', right: '#ffa58a' },
    subjects: [
      { slug: 'music', name: 'Music' },
      { slug: 'visual-arts', name: 'Visual Arts' },
      { slug: 'dance', name: 'Dancing', tab: 'Dance' },
      { slug: 'cooking', name: 'Cooking' },
      { slug: 'design', name: 'Design' },
      { slug: 'crafts', name: 'Crafts' },
      { slug: 'physical-ed', name: 'Physical Ed.', tab: 'P.E.' },
      { slug: 'mental-health', name: 'Mental Health' },
    ],
  },
};

// Tab colours, used in order (they repeat if a notebook has more subjects).
const TAB_COLORS = ['#ffc4dc', '#e6c4fa', '#b3aefc', '#ffc05a', '#e8ecaa', '#b8e68c', '#94cce4', '#90aee6'];

const coverVars = (c) => `--base:${c.base};--left:${c.left};--right:${c.right};`;
const esc = (t) => t.replace(/&/g, '&amp;').replace(/</g, '&lt;');

// ---------- curriculum.html: the three notebook covers ----------
function renderCovers(el) {
  el.innerHTML = Object.entries(NOTEBOOKS).map(([key, nb]) => `
    <div class="cover-wrap">
      <a class="cover" href="curriculum/${key}/" style="${coverVars(nb.cover)}">
        <span class="cover-title">${esc(nb.title)}</span>
      </a>
      <ul class="cover-pop">
        ${nb.subjects.map(s => `<li><a href="curriculum/${key}/#${s.slug}">${esc(s.name)}</a></li>`).join('')}
      </ul>
    </div>`).join('');
}

// ---------- curriculum/<notebook>/: open notebook with tabs ----------
function renderNotebook(key) {
  const nb = NOTEBOOKS[key];
  document.title = nb.title;
  document.getElementById('nb-title').textContent = nb.title;
  const book = document.getElementById('notebook');
  book.setAttribute('style', coverVars(nb.cover));

  const tabs = document.getElementById('tabs');
  tabs.innerHTML = nb.subjects.map((s, i) =>
    `<a class="tab" href="#${s.slug}" data-slug="${s.slug}" style="--tab:${TAB_COLORS[i % TAB_COLORS.length]}">${esc(s.tab || s.name)}</a>`
  ).join('');

  const page = document.getElementById('page');
  async function show() {
    const slug = location.hash.slice(1);
    const subject = nb.subjects.find(s => s.slug === slug) || nb.subjects[0];
    tabs.querySelectorAll('.tab').forEach(t => t.classList.toggle('active', t.dataset.slug === subject.slug));
    try {
      const res = await fetch(`curriculum/${key}/${subject.slug}.md`, { cache: 'no-cache' });
      if (!res.ok) throw new Error(res.status);
      page.innerHTML = marked.parse(await res.text());
    } catch {
      page.innerHTML = `<h1>${esc(subject.name)}</h1><p class="empty">Nothing here yet.</p>`;
    }
  }
  window.addEventListener('hashchange', show);
  show();
}
