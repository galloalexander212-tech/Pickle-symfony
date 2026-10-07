/* =====================================
   MOBILE MENU
===================================== */

const menuBtn = document.getElementById("menuBtn");
const mobileMenu = document.getElementById("mobileMenu");

if (menuBtn && mobileMenu) {

    menuBtn.addEventListener("click", function () {

        mobileMenu.classList.toggle("show");

    });

    const mobileLinks =
        document.querySelectorAll(".mobile-menu a");

    mobileLinks.forEach(function (link) {

        link.addEventListener("click", function () {

            mobileMenu.classList.remove("show");

        });

    });

}


/* =====================================
   BOOKING MODAL ELEMENTS
===================================== */

const bookingModal =
    document.getElementById("bookingModal");

const closeModal =
    document.getElementById("closeModal");

const courtSelect =
    document.getElementById("courtSelect");

const slotGrid =
    document.getElementById("slotGrid");

const slotHint =
    document.getElementById("slotHint");

const modalDate =
    document.getElementById("bookingDate");

const hiddenTime =
    document.getElementById("bookingTime");

const bookingMessage =
    document.getElementById("bookingMessage");


/* =====================================
   TIME SLOTS
===================================== */

const OPEN_HOUR = 6;    /* first slot 6:00 AM  */
const CLOSE_HOUR = 21;  /* last slot  9:00 PM  */


function formatSlotLabel(time) {

    const hour = parseInt(time.split(":")[0], 10);

    const suffix = hour >= 12 ? "PM" : "AM";

    const display = (hour % 12 === 0) ? 12 : (hour % 12);

    return display + ":00 " + suffix;
}


function isPastSlot(time, dateStr) {

    const now = new Date();

    const pad = function (n) {
        return String(n).padStart(2, "0");
    };

    const todayStr =
        now.getFullYear() + "-" +
        pad(now.getMonth() + 1) + "-" +
        pad(now.getDate());

    if (dateStr !== todayStr) {
        return false;
    }

    const hour = parseInt(time.split(":")[0], 10);

    return hour <= now.getHours();
}


function renderSlots(taken) {

    slotGrid.innerHTML = "";

    const dateStr = modalDate.value;

    for (let hour = OPEN_HOUR; hour <= CLOSE_HOUR; hour++) {

        const time =
            String(hour).padStart(2, "0") + ":00";

        const btn =
            document.createElement("button");

        btn.type = "button";

        btn.className = "slot-btn";

        btn.textContent = formatSlotLabel(time);

        btn.dataset.time = time;


        if (taken.includes(time)) {

            btn.disabled = true;

            btn.classList.add("taken");

            btn.title = "Already booked";

        } else if (isPastSlot(time, dateStr)) {

            btn.disabled = true;

            btn.classList.add("taken");

            btn.title = "Time already passed";
        }


        btn.addEventListener("click", function () {

            slotGrid.querySelectorAll(".slot-btn").forEach(function (other) {

                other.classList.remove("selected");

            });

            btn.classList.add("selected");

            hiddenTime.value = time;

            if (bookingMessage) {

                bookingMessage.textContent = "";

            }

        });


        slotGrid.appendChild(btn);

    }

}


function loadSlots() {

    if (!slotGrid || !courtSelect || !modalDate) {
        return;
    }

    /* Changing court or date clears the chosen slot */
    if (hiddenTime) {
        hiddenTime.value = "";
    }

    const court = courtSelect.value;
    const dateStr = modalDate.value;

    if (!court || !dateStr) {

        slotGrid.innerHTML = "";

        if (slotHint) {
            slotHint.textContent =
                "Choose a court and date to see available times.";
        }

        return;

    }

    if (slotHint) {
        slotHint.textContent = "Loading time slots...";
    }


    fetch(
        "database/slots.php?court=" +
        encodeURIComponent(court) +
        "&date=" +
        encodeURIComponent(dateStr)
    )

    .then(function (res) {
        return res.json();
    })

    .then(function (data) {

        renderSlots(data.taken || []);

        if (slotHint) {
            slotHint.textContent =
                "Tap a free time slot • Gray = taken";
        }

    })

    .catch(function () {

        renderSlots([]);

        if (slotHint) {
            slotHint.textContent =
                "Couldn't check booked slots — all shown as free.";
        }

    });

}


if (courtSelect) {
    courtSelect.addEventListener("change", loadSlots);
}

if (modalDate) {
    modalDate.addEventListener("change", loadSlots);
}


/* =====================================
   OPEN / CLOSE MODAL
===================================== */

function openBookingModal() {

    if (bookingModal) {

        bookingModal.classList.add("show");

        loadSlots();

    }

}


if (closeModal && bookingModal) {

    closeModal.addEventListener("click", function () {

        bookingModal.classList.remove("show");

    });

    bookingModal.addEventListener("click", function (event) {

        if (event.target === bookingModal) {

            bookingModal.classList.remove("show");

        }

    });

}


/* =====================================
   HERO BOOK BUTTON
===================================== */

const heroBookBtn =
    document.getElementById("heroBookBtn");

if (heroBookBtn) {

    heroBookBtn.addEventListener("click", function (event) {

        event.preventDefault();

        if (typeof IS_LOGGED_IN !== "undefined" && !IS_LOGGED_IN) {

            window.location.href =
                "database/index.php?notice=login-first";

            return;

        }

        if (courtSelect) {
            courtSelect.value = "";
        }

        openBookingModal();

    });

}


/* =====================================
   CARD BOOK BUTTONS
===================================== */

const bookButtons =
    document.querySelectorAll(".book-btn");


bookButtons.forEach(function (button) {

    button.addEventListener("click", function () {

        if (typeof IS_LOGGED_IN !== "undefined" && !IS_LOGGED_IN) {

            window.location.href =
                "database/index.php?notice=login-first";

            return;

        }

        const court =
            button.getAttribute("data-court");

        if (courtSelect) {
            courtSelect.value = court;
        }

        openBookingModal();

    });

});


/* =====================================
   COURT SEARCH
===================================== */

const searchBtn =
    document.getElementById("searchBtn");

const locationInput =
    document.getElementById("locationInput");

const searchMessage =
    document.getElementById("searchMessage");

const courtCards =
    document.querySelectorAll(".court-card");


if (searchBtn && locationInput && searchMessage) {

    searchBtn.addEventListener("click", function () {

        const searchValue =
            locationInput.value.trim().toLowerCase();

        let found = 0;


        courtCards.forEach(function (card) {

            const location =
                card.getAttribute("data-location");


            if (
                searchValue === "" ||
                location.includes(searchValue)
            ) {

                card.style.display = "block";

                found++;

            } else {

                card.style.display = "none";

            }

        });


        if (searchValue === "") {

            searchMessage.textContent =
                "Showing all available courts.";

        }

        else if (found > 0) {

            searchMessage.textContent =
                found +
                " court(s) found in " +
                locationInput.value +
                ".";

        }

        else {

            searchMessage.textContent =
                "No courts found. Try another location.";

        }

    });

}


/* =====================================
   BOOKING FORM SUBMIT
===================================== */

const bookingForm =
    document.getElementById("bookingForm");


if (bookingForm) {

    bookingForm.addEventListener("submit", function (event) {

        const time =
            hiddenTime ? hiddenTime.value : "";


        if (!courtSelect.value || !modalDate.value || !time) {

            event.preventDefault();

            if (bookingMessage) {

                bookingMessage.textContent =
                    "Please choose a court, date and time slot.";

            }

            return;

        }


        if (bookingMessage) {

            bookingMessage.textContent =
                "Sending your booking...";

        }

    });

}


/* =====================================
   SET MINIMUM BOOKING DATE
===================================== */

const dateInput =
    document.getElementById("dateInput");

const today =
    new Date().toISOString().split("T")[0];


if (dateInput) {
    dateInput.min = today;
}

if (modalDate) {
    modalDate.min = today;
}


/* =====================================
   FLASH MESSAGE AUTO-HIDE
===================================== */

const flashMsg =
    document.getElementById("flashMsg");

if (flashMsg) {

    setTimeout(function () {

        flashMsg.classList.add("hide");

    }, 5000);

}