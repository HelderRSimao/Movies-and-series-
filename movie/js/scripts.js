
function toggleSidebar() {
    const sidebar = document.getElementById("sidebar");
    sidebar.classList.toggle("open");

    if (sidebar.classList.contains("open")) {
        document.addEventListener("click", handleOutsideClick);
    } else {
        document.removeEventListener("click", handleOutsideClick);
    }
}

function handleOutsideClick(e) {
    const sidebar = document.getElementById("sidebar");
    const button = document.querySelector(".menu-btn");
    if (!sidebar.contains(e.target) && !button.contains(e.target)) {
        sidebar.classList.remove("open");
        document.removeEventListener("click", handleOutsideClick);
    }
}

function toggleDarkMode() {
    document.body.classList.toggle("dark");
    localStorage.setItem("darkMode", document.body.classList.contains("dark") ? "on" : "off");
}

window.onload = function() {
    if (localStorage.getItem("darkMode") === "on") {
        document.body.classList.add("dark");
    }
};
document.addEventListener("DOMContentLoaded", () => {
  const alertBox = document.querySelector('.alert');
  if (alertBox) {

    alertBox.style.transition = 'opacity 0.5s ease';

    setTimeout(() => {
      alertBox.style.opacity = '0';
      setTimeout(() => alertBox.remove(), 500);
    }, 3000);
  }
});