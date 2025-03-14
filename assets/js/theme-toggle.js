function toggleTheme() {
    const html = document.documentElement;
    const currentTheme = html.getAttribute("data-theme");
    if (currentTheme === "dark") {
      html.setAttribute("data-theme", "light");
      localStorage.setItem("theme", "light");
    } else {
      html.setAttribute("data-theme", "dark");
      localStorage.setItem("theme", "dark");
    }
    updateButton();
  }
  function updateButton() {
    const themeIcon = document.getElementById("theme-icon");
    const currentTheme = document.documentElement.getAttribute("data-theme");
    themeIcon.textContent = currentTheme === "dark" ? "☀️" : "🌙";
  }
  document.addEventListener("DOMContentLoaded", () => {
    const prefersDark = window.matchMedia("(prefers-color-scheme: dark)");
    const savedTheme = localStorage.getItem("theme");
    const initialTheme = savedTheme || (prefersDark.matches ? "dark" : "light");
    document.documentElement.setAttribute("data-theme", initialTheme);
    updateButton();
    prefersDark.addEventListener("change", (e) => {
      const newTheme = e.matches ? "dark" : "light";
      if (!savedTheme) document.documentElement.setAttribute("data-theme", newTheme);
      updateButton();
    });
  });