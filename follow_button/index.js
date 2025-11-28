document.addEventListener("DOMContentLoaded", () => {
  const path = window.location.pathname;

  // Check path starts with /properties
  if (!path.startsWith("/properties")) return;

  // Exclude /properties-jp
  if (path.startsWith("/properties-jp")) return;
  // Create main position container
  const btnPosition = document.createElement("div");
  btnPosition.id = "fixed-inquiry-button";
  btnPosition.className = "btnposition";

  // Create waku wrapper
  const waku = document.createElement("div");
  waku.className = "waku";

  // Create text
  const p = document.createElement("p");
  p.textContent = "- Free Consultation -";

  // Create button
  const button = document.createElement("button");
  button.className = "inquirebutton";
  button.type = "button";
  button.innerHTML = `Inquire Now!&emsp;&#x25B6;`;
  button.onclick = () => (location.href = "https://mrland.co.jp/contact/");

  // Create fukidashi wrapper
  const fuki = document.createElement("div");
  fuki.className = "inqfuki";

  // Create image
  const img = document.createElement("img");
  img.src = "./assets/hukidashi.png";

  // Append image to fuki
  fuki.appendChild(img);

  // Append elements to waku
  waku.appendChild(p);
  waku.appendChild(button);
  waku.appendChild(fuki);

  // Append waku to position container
  btnPosition.appendChild(waku);

  // Append to body
  document.body.appendChild(btnPosition);
  let scrollTimeout;
  const helpCard = document.getElementById("fixed-inquiry-button");
  const delay = 1500; // show after 1.5 seconds of no scrolling

  window.addEventListener("scroll", function () {
    // Hide card while scrolling
    helpCard.classList.remove("show");

    // Clear existing timer
    clearTimeout(scrollTimeout);

    // Start new timer
    scrollTimeout = setTimeout(() => {
      helpCard.classList.add("show");
    }, delay);
  });
});
