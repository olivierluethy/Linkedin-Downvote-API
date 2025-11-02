Herausfoderung waren vor allem bei der automatisation, den so einfach ging das nicht weil damit der Feed immer andere inhalte anzeigt, muss man mit dem Feed interagieren, darauf eine reaktion abgeben, den ansonsten kann es endlos immer das gleiche anzeigen.

For allem muss man immer sehr vielen Leuten folgen, damit immer ganz viele unterschiedliche Posts einem angezeigt werden.

In der Anfangsphase war es auch sehr schwierig wie man den Benutzer korrekt identifiziert. Vorher hatte man das über den localStorage gemacht, was dann später diskrepanzen zwischen der Art wie der content.js gearbeitet hat und das neue script.js welches dann zudem neu zusammen mit popup.html entstanden ist.

Um die Datenbank zu füllen hat man dieses Script entwickelt:
```javascript
// === AUTO-DOWNVOTE SCRIPT FÜR LINKEDIN (mit Like + Downvote für Feed-Wechsel) ===
// Funktioniert nur, wenn die Dislike-Extension aktiv ist!
// Nutzung: In DevTools (F12) → Console → Skript einfügen

(async function autoDownvoteLoop() {
  const DELAY_BETWEEN_ACTIONS = 900;   // ms zwischen Like & Downvote
  const DELAY_AFTER_SHOW_MORE = 2500;  // nach "Show more feed updates"
  const DELAY_AFTER_NEW_POSTS = 2000;  // nach "See new posts"
  const SCROLL_STEP = 800;             // Pixel pro Scroll-Schritt

  // Hilfsfunktion: Zufällige Verzögerung
  const sleep = (ms) => new Promise(resolve => setTimeout(resolve, ms + Math.random() * 400));

  // Prüft, ob Post bereits bearbeitet wurde
  function isAlreadyProcessed(postId) {
    const processed = JSON.parse(localStorage.getItem("linkdown-processed") || "[]");
    return processed.includes(postId);
  }

  // Speichert Post-ID als bearbeitet
  function markAsProcessed(postId) {
    const processed = JSON.parse(localStorage.getItem("linkdown-processed") || "[]");
    if (!processed.includes(postId)) {
      processed.push(postId);
      localStorage.setItem("linkdown-processed", JSON.stringify(processed));
    }
  }

  // Extrahiert Post-ID
  function extractPostId(postElement) {
    const urnRegex = /urn:li:(?:activity|share):(\d+)/i;

    let current = postElement;
    while (current && current !== document.body) {
      if (current.dataset?.urn) {
        const match = current.dataset.urn.match(urnRegex);
        if (match) return match[1];
      }
      if (current.dataset?.id) return current.dataset.id;
      current = current.parentElement;
    }

    const link = postElement.querySelector('a[href*="/activity/"], a[href*="/posts/"]');
    if (link?.href) {
      const url = link.href;
      const activityMatch = url.match(/activity[/-](\d+)/i);
      if (activityMatch) return activityMatch[1];
      const urnMatch = url.match(/urn:li:(?:activity|share):(\d+)/i);
      if (urnMatch) return urnMatch[1];
    }

    const urnEl = postElement.querySelector('[data-urn]');
    if (urnEl?.dataset?.urn) {
      const match = urnEl.dataset.urn.match(urnRegex);
      if (match) return match[1];
    }

    return `temp_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
  }

  // === NEU: Like + Downvote in einem Durchlauf ===
  async function interactWithPost(post) {
    const postId = extractPostId(post);
    if (isAlreadyProcessed(postId)) {
      console.log(`[SKIP] Post ${postId} bereits bearbeitet (Like + Downvote)`);
      return false;
    }

    // 1. Like-Button finden und klicken
    const likeButton = post.querySelector('button[aria-label="React Like"]');
    if (!likeButton) {
      console.log(`[FEHLER] Kein Like-Button in Post ${postId}`);
      return false;
    }

    // Prüfen, ob bereits geliked
    const likeLabel = likeButton.querySelector('span');
    if (likeLabel?.textContent.includes("Liked")) {
      console.log(`[SKIP] Post ${postId} bereits geliked`);
    } else {
      console.log(`[LIKE] Klicke auf Like für Post ${postId}`);
      likeButton.scrollIntoView({ behavior: "smooth", block: "center" });
      await sleep(300);
      likeButton.click();
      await sleep(DELAY_BETWEEN_ACTIONS);
    }

    // 2. Downvote-Button finden und klicken
    const downvoteButton = post.querySelector('.react-button__trigger[title="Downvote"]');
    if (!downvoteButton) {
      console.log(`[FEHLER] Kein Downvote-Button in Post ${postId}`);
      markAsProcessed(postId);
      return false;
    }

    const downvoteLabel = downvoteButton.querySelector('span');
    if (downvoteLabel?.textContent === "Downvoted") {
      console.log(`[SKIP] Post ${postId} bereits gedownvoted`);
      markAsProcessed(postId);
      return true;
    }

    console.log(`[DOWNVOTE] Klicke auf Downvote für Post ${postId}`);
    downvoteButton.scrollIntoView({ behavior: "smooth", block: "center" });
    await sleep(300);
    downvoteButton.click();
    await sleep(DELAY_BETWEEN_ACTIONS);

    if (downvoteLabel?.textContent === "Downvoted") {
      console.log(`[ERFOLG] Post ${postId} erfolgreich geliked + gedownvoted`);
      markAsProcessed(postId);
      return true;
    } else {
      console.warn(`[WARNUNG] Downvote für ${postId} möglicherweise fehlgeschlagen`);
      markAsProcessed(postId);
      return false;
    }
  }

  // Alle sichtbaren Posts verarbeiten
  async function processVisiblePosts() {
    const posts = Array.from(document.querySelectorAll(`
      div.feed-shared-update-v2,
      article,
      .occludable-update
    `)).filter(post => {
      const bar = post.querySelector('.feed-shared-social-action-bar');
      return bar && !processedPosts.has(post);
    });

    let processedCount = 0;
    for (const post of posts) {
      processedPosts.add(post);
      const success = await interactWithPost(post);
      if (success) processedCount++;
      if (processedCount > 0 && processedCount % 10 === 0) {
        console.log(`[FORTSCHRITT] ${totalProcessed + processedCount} Posts bearbeitet (Like + Downvote)`);
      }
    }

    return processedCount;
  }

  // Textbasierte Lade-Funktion
  async function loadMoreContent() {
    // 1. "Show more feed updates"
    const showMoreSpan = Array.from(document.querySelectorAll('span'))
      .find(span => span.textContent.trim() === "Show more feed updates");

    if (showMoreSpan) {
      let button = showMoreSpan;
      while (button && button.tagName !== 'BUTTON') {
        button = button.parentElement;
      }
      if (button && button.offsetParent !== null && !button.disabled) {
        console.log('[AKTION] Klicke auf "Show more feed updates"');
        button.scrollIntoView({ behavior: "smooth", block: "center" });
        await sleep(500);
        button.click();
        await sleep(DELAY_AFTER_SHOW_MORE);
        return true;
      }
    }

    // 2. "See new posts" (Textbasiert)
    const seeNewButtonByText = Array.from(document.querySelectorAll('button'))
      .find(btn => btn.textContent.trim() === "See new posts");

    if (seeNewButtonByText && seeNewButtonByText.offsetParent !== null && !seeNewButtonByText.disabled) {
      console.log('[AKTION] Klicke auf "See new posts"');
      seeNewButtonByText.scrollIntoView({ behavior: "smooth" });
      await sleep(500);
      seeNewButtonByText.click();
      await sleep(DELAY_AFTER_NEW_POSTS);
      return true;
    }

    // 3. Scrollen
    console.log('[AKTION] Kein Button → Scrolle...');
    window.scrollBy(0, SCROLL_STEP);
    await sleep(600);
    return false;
  }

  // Globale Zustände
  const processedPosts = new WeakSet();
  let totalProcessed = 0;
  let idleCount = 0;
  const MAX_IDLE = 5;

  console.log('Auto-Like+Downvote-Loop gestartet... (STRG+C zum Stoppen)');

  // === HAUPTSCHLEIFE ===
  while (true) {
    const processed = await processVisiblePosts();
    totalProcessed += processed;

    if (processed === 0) {
      idleCount++;
      console.log(`[IDLE ${idleCount}/${MAX_IDLE}] Keine neuen Posts...`);
    } else {
      idleCount = 0;
    }

    const contentLoaded = await loadMoreContent();

    if (idleCount >= MAX_IDLE && !contentLoaded) {
      console.log(`[PAUSE] ${MAX_IDLE} Leerlaufrunden – warte 7 Sekunden...`);
      await sleep(7000);
      idleCount = 0;
    }

    await sleep(1200);
  }
})();
```

Hier lag vor allem die Herausforderung darauf, was an alles machen muss, also pro Post auf welche Elemente man die moves verbinden muss, bis man dann hiermit schlussendlich den "Downvote" button einmalig tätigen konnte, und zwar nicht das danach später immer die gleichen posts angezeigt werden würden und das Skript nicht auf diesen Button klicken kann, da dieser schon für diesen Post geklickt wurde.

Auch stand die Herausforderung darin herauszufinden, wie man einem Post eine klare identifikation übergibt, wie man sie klar unterscheiden kann. Zuerst lag der Fokus daran auf eine ID, aber wie würde man es dann machen, dass der Post der bei einem anderen Benutzer angezeigt wird von der Erweiterung korrekt erkannt wird und wenn schon darauf jemand reagiert hatte, das es die gesamte Anzahl der Downvotes korrekt anzeigen würde?

Hierfür reichte es aus ChatGPT folgendes zu fragen:
```
Hat eigentlich jeder linkedin post seine eigene url, oder mittels welchen javascript code kann ich die identität von jedem linkedinpost erfahren oder einsehen?

Antwort:
Kurz: Ja — jeder LinkedIn-Post hat eine eindeutige Kennung (URN / activity id) und in der Regel eine eigene URL. Du kannst diese im DOM auslesen oder (komplizierter) über interne APIs holen — vorausgesetzt du bist auf `linkedin.com` eingeloggt und beachtest CORS / LinkedIn-Nutzungsbedingungen.

Unten habe ich dir ein robustes JavaScript-Snipppet für die Browser-Konsole (oder für eine Content-Script in einer Extension) zusammengestellt, das in der Feed-Ansicht möglichst viele Fälle abdeckt: es sucht nach bekannten Daten-Attributen, nach Links mit `/activity/` oder `/posts/` und baut, falls nötig, eine fallback-URL im Format `https://www.linkedin.com/feed/update/urn:li:activity:<id>`.

Kopier es in die Konsole auf einer LinkedIn-Seite (oder verwende es in einem content script):

```javascript
(function getLinkedInPostsFromDOM(){
  const posts = [];
  // mögliche Container-Selectoren für Beiträge im Feed
  const candidates = document.querySelectorAll(
    'article, div[data-urn], .feed-shared-update, .update-card, .occludable-update'
  );

  const urnRegex = /urn:li:(?:activity|share):(\d+)/i;

  candidates.forEach(el => {
    // 1) Suche nach data-urn / data-id direkt am Element oder in Eltern
    let urn = null;
    for (let node = el; node && node !== document; node = node.parentElement) {
      if (node.dataset && node.dataset.urn) { urn = node.dataset.urn; break; }
      // manchmal: data-id oder data-urnfull etc.
      if (node.getAttribute && node.getAttribute('data-urn')) { urn = node.getAttribute('data-urn'); break; }
      if (node.getAttribute && node.getAttribute('data-id')) { urn = node.getAttribute('data-id'); break; }
    }

    // 2) Falls noch kein urn: versuche Links im Beitrag (typisch: a[href*="/activity/"] oder /posts/)
    let url = null;
    const link = el.querySelector('a[href*="/activity/"], a[href*="/posts/"], a[data-control-name="comments"], a[data-control-name="open_share_details"]');
    if (link) {
      url = link.href;
      // versuche daraus eine urn zu extrahieren
      const m = url.match(/urn:li:(?:activity|share):\d+/i) || url.match(/activity-(\d+)/i);
      if (m) {
        // url kann /feed/update/urn:li:activity:123... enthalten
        const urnMatch = url.match(/urn:li:(?:activity|share):\d+/i);
        if (urnMatch) urn = urnMatch[0];
        else {
          const idMatch = url.match(/activity-(\d+)/i) || url.match(/posts\/.*-(\d+)(?:\?|$)/i);
          if (idMatch) urn = 'urn:li:activity:' + idMatch[1];
        }
      }
    }

    // 3) Falls noch keine URL aber urn vorhanden -> baue canonical feed/update URL
    if (!url && urn) {
      url = 'https://www.linkedin.com/feed/update/' + encodeURIComponent(urn);
    }

    // 4) Extrahiere eine lesbare ID (nur die Nummer) falls möglich
    let activityId = null;
    if (urn) {
      const um = urn.match(urnRegex);
      if (um) activityId = um[1];
    }

    // 5) Kurzer Text-/Autor-Snippet falls vorhanden
    const textSnippet = (el.querySelector('.feed-shared-update-v2__description, .feed-shared-text, .break-words') || el.querySelector('span, p'))?.textContent?.trim()?.slice(0,200) || null;
    const author = (el.querySelector('a[href*="/in/"], a.feed-shared-actor__name') || el.querySelector('.feed-shared-actor__name'))?.textContent?.trim() || null;

    // 6) Füge nur hinzu wenn wir irgendwas gefunden haben (Vermeidung von doppelten / nicht-post elements)
    if (urn || url || textSnippet) {
      posts.push({
        element: el,
        urn: urn || null,
        activityId: activityId,
        url: url || null,
        author: author,
        textSnippet: textSnippet
      });
    }
  });

  // entferne Duplikate nach activityId/url
  const unique = [];
  const seen = new Set();
  for (const p of posts) {
    const key = p.activityId || p.url || JSON.stringify([p.author, p.textSnippet]);
    if (!seen.has(key)) { seen.add(key); unique.push(p); }
  }

  console.log('Found posts:', unique.length);
  return unique;
})();
```

Was das Script liefert:

* `urn` (z. B. `urn:li:activity:1234567890`) — die interne Kennung des Posts, wenn vorhanden
* `activityId` — die reine numerische ID, extrahiert aus der URN
* `url` — eine bestmögliche URL zum Post (entweder ein Link, der im DOM gefunden wurde, oder eine gebaute Fallback-URL wie `/feed/update/urn:li:activity:...`)
* `author`, `textSnippet` als Hilfsinfos
* `element` ist das DOM-Element des Posts (falls du weiter manipulieren willst)

Wichtige Hinweise / Grenzen

* Du musst dieses Script **auf linkedin.com ausführen** (z. B. als Content Script oder in der DevTools-Konsole), sonst führen Cross-Origin-Beschränkungen und fehlende DOM-Struktur ins Leere.
* LinkedIn ändert HTML/Selectors regelmäßig — das Script ist robust gehalten, aber einzelne Selector-Namen können sich ändern (dann musst du Anpassungen vornehmen).
* Interne APIs (Voyager, /feed/update/..., GraphQL) liefern oft strukturierte JSON-Daten, sind aber **auth-geschützt** und können CSRF-Token erfordern; außerdem ist ihre Nutzung in Bulk u. U. gegen die Nutzungsbedingungen. Für Extension-Projekte ist es üblicher, Content-Scripts zu verwenden oder offizielle APIs (falls verfügbar) zu nutzen.
* Achte auf Datenschutz und LinkedIn-ToS, wenn du Daten speicherst/scrapest.

Wenn du magst, kann ich dir:

* ein angepasstes Content-Script für eine Chrome-Extension schreiben, das diese Infos regelmäßig sammelt und in `chrome.storage` speichert; oder
* ein Snippet bauen, das zusätzlich die vollständige OpenGraph/Meta-Information eines einzelnen Posts (Titel, image, canonical) ausliest — falls du die Post-Seite auf hast.

Sag mir kurz, ob du das als **Einmal-Konsolen-Tool** willst oder als **Content Script / Extension** — dann liefere ich dir das passende vollständige Beispiel.

```

Dies wiederspiegelte sich das Verständnis zu, wie man jedes einzelnen Post miteinander unterscheiden kann. Diese Identifikation rutschte dann auch zeitgleich in die DB hinein.