const API_BASE = window.BACKEND_ORIGIN || "http://localhost:8000";

const selectors = {
  authUser: document.getElementById("auth-user"),
  loginBtn: document.getElementById("login-btn"),
  logoutBtn: document.getElementById("logout-btn"),
  flashSuccess: document.getElementById("flash-success"),
  flashError: document.getElementById("flash-error"),
  createForm: document.getElementById("create-form"),
  uidInput: document.getElementById("uid-input"),
  vibeInput: document.getElementById("vibe-input"),
  searchForm: document.getElementById("search-form"),
  searchQuery: document.getElementById("search-query"),
  searchTopK: document.getElementById("search-top-k"),
  searchResults: document.getElementById("search-results"),
  resultsHeading: document.getElementById("results-heading"),
  resultsList: document.getElementById("results-list"),
  vibeList: document.getElementById("vibe-list"),
  noVibes: document.getElementById("no-vibes"),
};

let currentUser = null;

function setFlash(type, message) {
  const element = type === "error" ? selectors.flashError : selectors.flashSuccess;
  const opposite = type === "error" ? selectors.flashSuccess : selectors.flashError;

  if (opposite) {
    opposite.hidden = true;
    opposite.textContent = "";
  }

  if (!element) {
    return;
  }
  if (!message) {
    element.hidden = true;
    element.textContent = "";
    return;
  }
  element.textContent = message;
  element.hidden = false;
}

async function fetchJSON(path, options = {}) {
  const response = await fetch(`${API_BASE}${path}`, {
    credentials: "include",
    headers: {
      "Content-Type": "application/json",
      ...(options.headers || {}),
    },
    ...options,
  });

  if (response.status === 204) {
    return null;
  }

  const text = await response.text();
  let payload;
  try {
    payload = text ? JSON.parse(text) : null;
  } catch (error) {
    throw new Error(`Failed to parse JSON response from ${path}`);
  }

  if (!response.ok) {
    const message = payload?.detail || payload?.error || `Request failed (${response.status})`;
    throw new Error(message);
  }

  return payload;
}

function updateAuthUI(user) {
  currentUser = user;
  if (!user) {
    selectors.authUser.textContent = "Not signed in";
    selectors.logoutBtn.hidden = true;
    selectors.loginBtn.hidden = false;
    return;
  }

  selectors.authUser.textContent = `Signed in as ${user.preferred_name || user.email}`;
  selectors.uidInput.value = user.intra_login || user.email || "";
  selectors.logoutBtn.hidden = false;
  selectors.loginBtn.hidden = true;
}

function renderVibes(vibes) {
  selectors.vibeList.innerHTML = "";
  if (!vibes || vibes.length === 0) {
    selectors.noVibes.hidden = false;
    return;
  }
  selectors.noVibes.hidden = true;

  vibes.forEach((item) => {
    const li = document.createElement("li");
    li.innerHTML = `
      <span class="uid">${item.uid}</span>
      <span class="timestamp">updated ${item.updated_at || "unknown"}</span>
      <p class="vibe-text">${item.original_vibe}</p>
      <p class="vibe-processed">processed: ${item.processed_vibe}</p>
      <span class="model">${item.embedding_model}</span>
    `;
    selectors.vibeList.appendChild(li);
  });
}

function renderSearchResults(results, query) {
  selectors.resultsList.innerHTML = "";
  if (selectors.resultsHeading) {
    selectors.resultsHeading.textContent = query ? `Results for "${query}"` : "Results";
  }
  if (!results || results.length === 0) {
    selectors.searchResults.hidden = false;
    selectors.resultsList.innerHTML = `<li>No vibes matched "${query}".</li>`;
    return;
  }
  selectors.searchResults.hidden = false;

  results.forEach((item) => {
    const li = document.createElement("li");
    li.innerHTML = `
      <span class="uid">${item.uid}</span>
      <span class="score">score ${item.formatted_score}</span>
      ${
        item.overlap_terms && item.overlap_terms.length
          ? `<span class="score-details">lexical terms: ${item.overlap_terms.join(", ")}</span>`
          : ""
      }
      <p class="vibe-text">${item.original_vibe}</p>
      <p class="vibe-processed">processed: ${item.processed_vibe}</p>
      <span class="model">${item.embedding_model}</span>
    `;
    selectors.resultsList.appendChild(li);
  });
}

async function refreshAll() {
  try {
    const user = await fetchJSON("/api/v1/me").catch(() => null);
    updateAuthUI(user);
  } catch (error) {
    console.error(error);
    updateAuthUI(null);
  }

  try {
    const vibes = await fetchJSON("/api/v1/vibes?limit=20");
    renderVibes(vibes);
  } catch (error) {
    setFlash("error", error.message);
  }
}

selectors.createForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  const uid = selectors.uidInput.value.trim();
  const vibe = selectors.vibeInput.value.trim();
  if (!uid || !vibe) {
    setFlash("error", "UID and vibe text are required.");
    return;
  }

  try {
    await fetchJSON("/api/v1/vibes", {
      method: "POST",
      body: JSON.stringify({ uid, vibe_text: vibe }),
    });
    setFlash("success", `Saved vibe for ${uid}.`);
    selectors.vibeInput.value = "";
    await refreshAll();
  } catch (error) {
    setFlash("error", error.message);
  }
});

selectors.searchForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  const query = selectors.searchQuery.value.trim();
  const topK = Number.parseInt(selectors.searchTopK.value || "5", 10);
  if (!query) {
    setFlash("error", "Search query is required.");
    return;
  }

  try {
    const results = await fetchJSON("/api/v1/search", {
      method: "POST",
      body: JSON.stringify({ query, top_k: topK }),
    });
    renderSearchResults(results, query);
    setFlash("success", `Found ${results.length} result(s) for "${query}".`);
  } catch (error) {
    setFlash("error", error.message);
  }
});

selectors.loginBtn.addEventListener("click", () => {
  window.location.href = `${API_BASE}/login`;
});

selectors.logoutBtn.addEventListener("click", async () => {
  try {
    await fetchJSON("/api/v1/logout", { method: "POST" });
    setFlash("success", "Logged out.");
    updateAuthUI(null);
  } catch (error) {
    setFlash("error", error.message);
  }
});

refreshAll();
