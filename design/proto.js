(function () {
  var root = document.documentElement;
  if (localStorage.getItem("carapi-design-theme") === "dark") root.dataset.theme = "dark";

  document.querySelectorAll(".frame").forEach(function (frame) {
    var tpl = document.getElementById("screen");
    if (!tpl) return;
    var node = tpl.content.cloneNode(true);
    var suffix = "-" + frame.dataset.frame;
    node.querySelectorAll("[id]").forEach(function (el) {
      el.id = el.id + suffix;
    });
    node.querySelectorAll("[for]").forEach(function (el) {
      el.setAttribute("for", el.getAttribute("for") + suffix);
    });
    node.querySelectorAll('[href^="#"]').forEach(function (el) {
      var id = el.getAttribute("href").slice(1);
      if (id) el.setAttribute("href", "#" + id + suffix);
    });
    frame.appendChild(node);
  });

  document.addEventListener("click", function (event) {
    var theme = event.target.closest("[data-theme-toggle]");
    if (theme) {
      root.dataset.theme = root.dataset.theme === "dark" ? "light" : "dark";
      localStorage.setItem("carapi-design-theme", root.dataset.theme);
    }
    var burger = event.target.closest("[data-burger]");
    if (burger) {
      var panel = (burger.closest(".frame") || document).querySelector(".burger-panel");
      if (panel) panel.classList.toggle("is-open");
    }
    var docs = event.target.closest("[data-docs-toggle]");
    if (docs) {
      var side = (docs.closest(".frame") || document).querySelector(".docs-side");
      if (side) side.classList.toggle("is-open");
    }
    var copy = event.target.closest("[data-copy]");
    if (copy) {
      var original = copy.dataset.label || copy.textContent;
      copy.dataset.label = original;
      copy.textContent = "ტექსტი დაკოპირებულია";
      window.setTimeout(function () { copy.textContent = original; }, 2000);
    }
    var tab = event.target.closest("[data-tab]");
    if (tab) {
      var bar = tab.closest(".code");
      bar.querySelectorAll("[data-tab]").forEach(function (button) {
        button.setAttribute("aria-selected", button === tab ? "true" : "false");
      });
      bar.querySelectorAll("[data-panel]").forEach(function (panel) {
        panel.hidden = panel.dataset.panel !== tab.dataset.tab;
      });
    }
    var eye = event.target.closest("[data-eye]");
    if (eye) {
      var input = eye.parentElement.querySelector("input");
      var show = input.type === "password";
      input.type = show ? "text" : "password";
      eye.textContent = show ? "პაროლის დამალვა" : "პაროლის ჩვენება";
      eye.setAttribute("aria-label", eye.textContent);
    }
    var open = event.target.closest("[data-modal-open]");
    if (open) {
      var scope = open.closest(".frame") || document;
      var key = open.getAttribute("data-modal-open");
      var modal = scope.querySelector('[data-modal][id="' + key + '"], [data-modal][id^="' + key + '-"]');
      if (modal) {
        modal.hidden = false;
        var focus = modal.querySelector("button, [href], input");
        if (focus) focus.focus();
      }
    }
    var close = event.target.closest("[data-modal-close]");
    if (close || event.target.classList.contains("modal-back")) {
      var back = event.target.closest("[data-modal]");
      if (back && (close || event.target === back)) back.hidden = true;
    }
    var combo = event.target.closest("[data-combo]");
    if (combo && !combo.disabled) {
      var list = combo.parentElement.querySelector(".menu-list");
      if (list) list.hidden = !list.hidden;
    }
    var option = event.target.closest("[data-option]");
    if (option) {
      var box = option.closest(".step, .widget-box");
      var button = box.querySelector("[data-combo]");
      button.textContent = option.textContent;
      option.parentElement.querySelectorAll("[data-option]").forEach(function (item) {
        item.setAttribute("aria-selected", item === option ? "true" : "false");
      });
      option.parentElement.hidden = true;
    }
  });

  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") {
      document.querySelectorAll("[data-modal]").forEach(function (modal) { modal.hidden = true; });
      document.querySelectorAll(".burger-panel, .docs-side").forEach(function (panel) { panel.classList.remove("is-open"); });
    }
  });

  document.addEventListener("input", function (event) {
    var search = event.target.closest("[data-doc-search]");
    if (search) {
      var query = search.value.trim().toLowerCase();
      search.closest(".docs-side").querySelectorAll("a").forEach(function (link) {
        link.hidden = query.length > 0 && link.textContent.toLowerCase().indexOf(query) === -1;
      });
    }
    var demo = event.target.closest("[data-demo-search]");
    if (demo) {
      var list = demo.parentElement.querySelector(".menu-list");
      var q = demo.value.trim().toLowerCase();
      list.querySelectorAll("[data-option]").forEach(function (item) {
        item.hidden = q.length >= 2 && item.textContent.toLowerCase().indexOf(q) === -1;
      });
    }
  });

  document.addEventListener("submit", function (event) { event.preventDefault(); });
})();
