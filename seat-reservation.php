<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Select Your Seat</title>
    <link rel="stylesheet" href="css/seats.css" />
</head>
<body>
    <div class="container">
        <aside class="timings">
            <h3>Available Timings</h3>
            <ul id="timing-list">
                <?php
                $timings = ["06:30 AM", "09:30 AM", "12:00 PM", "04:30 PM", "08:00 PM"];
                foreach ($timings as $index => $time) {
                    $activeClass = ($index === 0) ? "active" : "";
                    echo "<li tabindex='0' role='button' class='$activeClass' data-time='$time'><span class='icon'>⏰</span> $time</li>";
                }
                ?>
            </ul>
        </aside>

        <main class="seat-selection">
            <h1>Select Your Seat</h1>
            <div class="screen"></div>

            <div class="seats-wrapper">
                <?php
                $rows = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];
                foreach ($rows as $row):
                    echo '<div class="row"><div class="row-label">' . $row . '</div>';
                    $totalSeats = 18;
                    for ($i = 1; $i <= $totalSeats; $i++):
                        if (($row === 'A' || $row === 'B') && $i > 9) {
                            if ($i == 10) echo '<div class="seat-gap"></div>';
                            continue;
                        }
                        if ($i == 10) echo '<div class="seat-gap"></div>';

                        // Define which seats are booked
                        $isBooked = false;
                        if ($row === 'D' && ($i === 7 || $i === 8)) $isBooked = true;

                        // Define selected seat on B7 as default selected
                        $isSelected = ($row === 'B' && $i === 7) ? true : false;

                        $classes = "seat";
                        if ($isSelected) $classes .= " selected";
                        if ($isBooked) $classes .= " booked";
                        $disabled = $isBooked ? "aria-disabled='true' tabindex='-1'" : "tabindex='0' role='checkbox' aria-checked='" . ($isSelected ? "true" : "false") . "'";
                        $dataAttr = "data-seat='$row$i'";
                        echo "<div class='$classes' $disabled $dataAttr></div>";
                    endfor;
                    echo '</div>';
                endforeach;
                ?>

                <!-- Seat numbers below all rows -->
                <div class="row row-labels seat-numbers-bottom">
                    <div></div>
                    <?php for ($i = 1; $i <= 18; $i++): ?>
                        <?php if ($i == 10): ?>
                            <div class="seat-gap"></div>
                        <?php endif; ?>
                        <div class="seat-number"><?php echo $i; ?></div>
                    <?php endfor; ?>
                </div>
            </div>

            <button id="proceed-btn" class="proceed-btn" disabled>
                Proceed to checkout <span>→</span>
            </button>
        </main>
    </div>

    <script>
        // Timing selection
        const timingList = document.getElementById('timing-list');
        const timingItems = timingList.querySelectorAll('li');

        timingList.addEventListener('click', e => {
            if (e.target && e.target.tagName === 'LI' && !e.target.classList.contains('active')) {
                timingItems.forEach(li => li.classList.remove('active'));
                e.target.classList.add('active');
            }
        });

        // Seat selection
        const seatsWrapper = document.querySelector('.seats-wrapper');
        const seats = seatsWrapper.querySelectorAll('.seat:not(.booked)');
        const proceedBtn = document.getElementById('proceed-btn');

        let selectedSeats = new Set();

        seats.forEach(seat => {
            seat.addEventListener('click', () => {
                const seatId = seat.getAttribute('data-seat');
                if (seat.classList.contains('selected')) {
                    seat.classList.remove('selected');
                    seat.setAttribute('aria-checked', 'false');
                    selectedSeats.delete(seatId);
                } else {
                    seat.classList.add('selected');
                    seat.setAttribute('aria-checked', 'true');
                    selectedSeats.add(seatId);
                }
                proceedBtn.disabled = selectedSeats.size === 0;
            });

            // Keyboard accessibility
            seat.addEventListener('keydown', (e) => {
                if (e.key === ' ' || e.key === 'Enter') {
                    e.preventDefault();
                    seat.click();
                }
            });
        });

        // Proceed button
        proceedBtn.addEventListener('click', () => {
            alert('Selected timing: ' + document.querySelector('.timings .active').dataset.time + '\nSelected Seats: ' + [...selectedSeats].join(', '));
        });
    </script>
</body>
</html>
