function checkValidation() {
  const form = document.querySelector("form.cart");
  const addToCartBtn = document.querySelector(".single_add_to_cart_button");
  const snakeForms = document.querySelectorAll(".snake-form");
  if (snakeForms.length === 0) {
    addToCartBtn.style.display = "none";
    return;
  }

  let allValid = true;
  snakeForms.forEach((snake) => {
    const checked = snake.querySelectorAll('input[type="checkbox"]:checked');
    if (checked.length === 0) {
      allValid = false;
    }
  });
  addToCartBtn.style.display = allValid ? "inline-block" : "none";
}
document.addEventListener("DOMContentLoaded", function () {
  // Watch for checkbox changes
  document.body.addEventListener("change", function (e) {
    if (e.target.matches('.snake-form input[type="checkbox"]')) {
      checkValidation();
    }
  });

  // Watch for add-snake button clicks (if you have one)
  document.body.addEventListener("click", function (e) {
    if (e.target.matches(".add-snake-button")) {
      setTimeout(checkValidation, 100); // slight delay for DOM update
    }
  });

  // Initial state
  checkValidation();
  if (document.getElementById("drop-zone")) {
    const fileInput = document.getElementById("csv-upload");
    const dropZone = document.getElementById("drop-zone");
    const uploadBtn = document.getElementById("upload-btn");

    // Trigger file input when clicking button
    dropZone.addEventListener("click", () => fileInput.click());

    // File input change
    fileInput.addEventListener("change", handleFiles);

    // Drag and drop events
    dropZone.addEventListener("dragover", (e) => {
      e.preventDefault();
      dropZone.classList.add("dragover");
    });

    dropZone.addEventListener("dragleave", () => {
      dropZone.classList.remove("dragover");
    });

    dropZone.addEventListener("drop", (e) => {
      e.preventDefault();
      dropZone.classList.remove("dragover");

      const files = e.dataTransfer.files;
      if (files.length > 0) {
        fileInput.files = files; // Set file input manually
        handleFiles({ target: { files } }); // Simulate change event
      }
    });
  }
  function handleFiles(event) {
    const files = event.target.files;
    if (!files.length) return alert("Please select a CSV file.");

    const reader = new FileReader();
    reader.onload = function (e) {
      const csvData = Papa.parse(e.target.result.trim(), {
        header: true,
        skipEmptyLines: true,
      });

      csvData.data.forEach((row) => {
        const snakeId = row["Snake ID"] || "";
        const knownGenetics = row["Known Genetics"] || "";
        const tests = row["Test"] || "";

        const testArray = tests.split(",").map((t) => t.trim());
        const snakeForm = addSnakeFormCsv(snakeId, knownGenetics, testArray);
        updateSnakePrice(snakeForm);
      });

      updateTotalPrice();
      fileInput.value = "";
    };

    reader.readAsText(files[0]);
  }
});

const priceMap = snake_test_ajax.prices;
const fullPanelPrice = Number(snake_test_ajax.fullPanelPrice);
const recessives = snake_test_ajax.recessives;
const threshold = Number(snake_test_ajax.full_panel_threshold);

let snakeIndex = 1;

function updateSnakePrice(formEl) {
  const tests = formEl.querySelectorAll(".genetic-test");
  const priceEl = formEl.querySelector(".price-display");
  const pricePerTestEl = formEl.querySelector(".price-per-test");

  let selected = Array.from(tests).filter((c) => c.checked);
  let count = selected.length;
  let price = 0;

  if (count >= threshold) {
    // Select all checkboxes (full panel)
    tests.forEach((cb) => (cb.checked = true));
    price = fullPanelPrice;

    // 🔄 Recalculate selected and count
    selected = Array.from(tests);
    count = selected.length;
  } else if (count > 0) {
    price = Number(priceMap[count]) || 0;
  }

  // Update total price
  priceEl.textContent = `$${price.toFixed(2)}`;

  // ✅ Update price per test
  if (count > 0) {
    const perTest = price / count;
    pricePerTestEl.textContent = `$${perTest.toFixed(2)}`;
  } else {
    pricePerTestEl.textContent = `$0.00`;
  }

  updateTotalPrice();
}

function updateTotalPrice() {
  let total = 0;
  document.querySelectorAll(".snake-form").forEach((form) => {
    let priceText = form
      .querySelector(".price-display")
      .textContent.replace("$", "");
    total += parseFloat(priceText);
  });
  let addToCartBtn = document.querySelector(".single_add_to_cart_button");
  setTimeout(function () {
    checkValidation();
  }, 500);
  if (total.toFixed(2) > 0) {
    addToCartBtn.style.display = "inline-block";
  } else {
    addToCartBtn.style.display = "none";
  }
  document.getElementById("grand-total").textContent = `$${total.toFixed(2)}`;
}

function selectAll(formEl) {
  formEl.querySelectorAll(".genetic-test").forEach((cb) => (cb.checked = true));
  updateSnakePrice(formEl);
}

function selectRecessives(formEl) {
  formEl
    .querySelectorAll(".genetic-test")
    .forEach((cb) => (cb.checked = false));
  formEl.querySelectorAll(".genetic-test").forEach((cb) => {
    if (recessives.includes(cb.value)) cb.checked = true;
  });
  updateSnakePrice(formEl);
}

function deselectAll(formEl) {
  formEl
    .querySelectorAll(".genetic-test")
    .forEach((cb) => (cb.checked = false));
  updateSnakePrice(formEl);
}

function addSnakeFormCsv(snakeId = "", knownGenetics = "", testArray = "") {
  const templateEl = document.getElementById("snake-form-template");
  if (!templateEl) {
    console.error("Template element not found");
    return;
  }

  const snakeFormHTML = templateEl.innerHTML.trim();
  const formWrapper = document.createElement("div");
  formWrapper.innerHTML = snakeFormHTML;

  const formEl = formWrapper.querySelector(".snake-form");
  if (!formEl) {
    console.error("Could not find .snake-form in rendered HTML");
    return;
  }

  formEl.setAttribute("data-index", snakeIndex);
  formEl.querySelectorAll("input").forEach((input) => {
    input.name = input.name.replace("[0]", `[${snakeIndex}]`);
  });
  if (snakeId) formEl.querySelector(".snake-id-input").value = snakeId;
  if (knownGenetics)
    formEl.querySelector(".genetics-input").value = knownGenetics;
  //  Select relevant tests
  if (testArray) {
    testArray.forEach((testName) => {
      const checkbox = [...formEl.querySelectorAll(".genetic-test")].find(
        (cb) =>
          cb.nextSibling?.textContent?.trim().toLowerCase() ===
          testName.toLowerCase()
      );
      if (checkbox) checkbox.checked = true;
    });
  }
  document.getElementById("snake-forms").appendChild(formEl);
  snakeIndex++;
  return formEl;
}

function addSnakeForm() {
  const templateEl = document.getElementById("snake-form-template");
  if (!templateEl) {
    console.error("Template element not found");
    return;
  }

  const snakeFormHTML = templateEl.innerHTML.trim();
  const formWrapper = document.createElement("div");
  formWrapper.innerHTML = snakeFormHTML;

  const template = formWrapper.querySelector(".snake-form");

  template.setAttribute("data-index", snakeIndex);
  template.querySelectorAll("input").forEach((input) => {
    input.name = input.name.replace("[0]", `[${snakeIndex}]`);
    if (input.type === "text") input.value = "";
    if (input.type === "checkbox") input.checked = false;
  });
  template.querySelector(".price-display").textContent = "$0.00";
  document.getElementById("snake-forms").appendChild(template);

  snakeIndex++;

  const snakeCount = document.querySelectorAll(".snake-form").length;
  // const btn = document.getElementById('add-snake-btn');

  // if (snakeCount === 0) {
  //     btn.textContent = '+ Add First Snake';
  // } else {
  //     btn.textContent = '+ Add Another Snake';
  // }
}

function deleteSnakeForm(button) {
  // Get the snake form element to delete
  const form = button.closest(".snake-form");
  if (!form) return;

  // Remove the form from DOM
  form.remove();

  // Reindex the remaining forms
  const forms = document.querySelectorAll(".snake-form");
  forms.forEach((formEl, index) => {
    formEl.dataset.index = index;

    formEl.querySelectorAll("input, label").forEach((input) => {
      if (input.name) {
        // Update index in name attribute (e.g., snakes[2][id] => snakes[0][id])
        input.name = input.name.replace(/\[\d+]/g, `[${index}]`);
      }
    });
  });

  // Optional: Update total price or any other UI element
  updateTotalPrice();
  const snakeCount = document.querySelectorAll(".snake-form").length;
  // const btn = document.getElementById('add-snake-btn');

  // if (snakeCount === 0) {
  //     btn.textContent = '+ Add First Snake';
  // } else {
  //     btn.textContent = '+ Add Another Snake';
  // }
  snakeIndex = snakeCount;
}

document.addEventListener("DOMContentLoaded", () => {
  document.addEventListener("click", function (e) {
    const isActionButton =
      e.target.classList.contains("select-all-btn") ||
      e.target.classList.contains("select-recessive-btn") ||
      e.target.classList.contains("deselect-all-btn");

    if (isActionButton) {
      const form = e.target.closest(".snake-form");

      // Remove 'active' class from all three buttons within the same form
      form
        .querySelectorAll(
          ".select-all-btn, .select-recessive-btn, .deselect-all-btn"
        )
        .forEach((btn) => {
          btn.classList.remove("active");
        });

      // Add 'active' to the clicked button
      e.target.classList.add("active");
    }

    if (e.target.classList.contains("select-all-btn")) {
      const form = e.target.closest(".snake-form");
      selectAll(form);
    }

    if (e.target.classList.contains("select-recessive-btn")) {
      const form = e.target.closest(".snake-form");
      selectRecessives(form);
    }

    if (e.target.classList.contains("deselect-all-btn")) {
      const form = e.target.closest(".snake-form");
      deselectAll(form);
    }
  });

  document.addEventListener("change", function (e) {
    if (e.target.classList.contains("genetic-test")) {
      const form = e.target.closest(".snake-form");
      const checked = form.querySelectorAll('input[type="checkbox"]:checked');
      if (
        form
          .querySelector(".select-recessive-btn").classList.contains("active")
      ) {
        if (checked.length > recessives.length) {
              e.target.checked = false;
          return;
        }
        if (recessives.includes(e.target.value)) {
          e.target.checked = true;
        }
      }

      if (
        form
          .querySelector(".deselect-all-btn").classList.contains("active")
      ) {
        console.log(checked.length);
        if (checked.length > 10) {
          e.target.checked = false;
          return;
        }
      }

      updateSnakePrice(form);
    }
  });
});
