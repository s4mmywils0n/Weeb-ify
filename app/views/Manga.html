<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weeb Database</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f4f4f9;
            color: #333;
            margin: 0;
            padding: 30px;
        }

        .container {
            max-width: 1100px;
            margin: auto;
        }

        h1 {
            text-align: center;
            color: #c3a22b;
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin: 25px 0;
        }

        button {
            padding: 10px 18px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 15px;
        }

        .tab {
            background: #ddd;
        }

        .tab.active {
            background: #6c4ab6;
            color: white;
        }

        .panel {
            background: white;
            padding: 20px;
            margin-bottom: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px #00000010;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        input, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        .submit-btn {
            margin-top: 15px;
            background: #6c4ab6;
            color: white;
        }

        .refresh-btn {
            background: #e5e0f5;
            color: #493080;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th, td {
            text-align: left;
            padding: 12px;
            border-bottom: 1px solid #eee;
        }

        th {
            background: #6c4ab6;
            color: white;
        }

        tr:hover {
            background: #f8f6fc;
        }

        .message {
            margin-top: 12px;
            white-space: pre-wrap;
        }

        @media (max-width: 600px) {
            body {
                padding: 15px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
<div class="container">

    <h1>Weeb: ify </h1>
    <p style="text-align:center">
        Manage your anime and manga collection.
    </p>

    <div class="tabs">
        <button class="tab active" id="animeTab"
                onclick="switchResource('anime')">
            Anime
        </button>

        <button class="tab" id="mangaTab"
                onclick="switchResource('manga')">
            Manga
        </button>
    </div>

    <section class="panel">
        <h2 id="formTitle">Add Anime</h2>

        <form id="recordForm">
            <div class="form-grid">
                <input name="title" placeholder="Title" required>

                <div id="animeFields" class="form-grid">
                    <input name="season_aired"
                           placeholder="Season aired">

                    <input name="year_aired"
                           type="number"
                           placeholder="Year aired">

                    <input name="number_of_episodes"
                           type="number"
                           min="0"
                           placeholder="Number of episodes">

                    <input name="number_of_seasons"
                           type="number"
                           min="0"
                           placeholder="Number of seasons">

                    <input name="studio" placeholder="Studio">
r
                    <input name="genre" placeholder="Genre">
                </div>

                <div id="mangaFields" class="form-grid" hidden>
                    <input name="author" placeholder="Author">

                    <input name="year_published"
                           type="number"
                           placeholder="Year published">

                    <input name="number_of_volumes"
                           type="number"
                           min="0"
                           placeholder="Number of volumes">

                    <input name="number_of_chapters"
                           type="number"
                           min="0"
                           placeholder="Number of chapters">

                    <input name="manga_genre"
                           placeholder="Genre">
                </div>

                <select name="status" required>
                    <option value="">Select status</option>
                    <option value="Watching">Watching</option>
                    <option value="Reading">Reading</option>
                    <option value="Completed">Completed</option>
                    <option value="On Hold">On Hold</option>
                    <option value="Dropped">Dropped</option>
                    <option value="Plan to Watch">Plan to Watch</option>
                    <option value="Plan to Read">Plan to Read</option>
                </select>
            </div>

            <button class="submit-btn" type="submit">
                Add Record
            </button>

            <div class="message" id="formMessage"></div>
        </form>
    </section>

    <section class="panel">
        <h2 id="listTitle">Anime Collection</h2>

        <button class="refresh-btn" onclick="loadRecords()">
            Refresh Records
        </button>

        <div class="message" id="listMessage"></div>

        <div class="table-wrapper">
            <table>
                 <thead id="tableHead"></thead>
                <tbody id="tableBody"></tbody>
            </table>
        </div>
    </section>

</div>

<script>
    // Change this if your PHP API is in another folder.
    const API_URL = "./api.php";

    let currentResource = "anime";

    const animeColumns = [
        ["id", "ID"],
        ["title", "Title"],
        ["season_aired", "Season Aired"],
        ["year_aired", "Year"],
        ["number_of_episodes", "Episodes"],
        ["number_of_seasons", "Seasons"],
        ["studio", "Studio"],
        ["genre", "Genre"],
        ["status", "Status"]
    ];

    const mangaColumns = [
        ["id", "ID"],
        ["title", "Title"],
        ["author", "Author"],
        ["year_published", "Year Published"],
        ["number_of_volumes", "Volumes"],
        ["number_of_chapters", "Chapters"],
        ["genre", "Genre"],
        ["status", "Status"]
    ];

    function switchResource(resource) {
        currentResource = resource;

        document.getElementById("animeTab")
            .classList.toggle("active", resource === "anime");

        document.getElementById("mangaTab")
            .classList.toggle("active", resource === "manga");

        document.getElementById("animeFields").hidden =
            resource !== "anime";

        document.getElementById("mangaFields").hidden =
            resource !== "manga";

        document.getElementById("formTitle").textContent =
            "Add " + (resource === "anime" ? "Anime" : "Manga");

        document.getElementById("listTitle").textContent =
            (resource === "anime" ? "Anime" : "Manga")
            + " Collection";

        document.getElementById("formMessage").textContent = "";

        loadRecords();
    }

    async function loadRecords() {
        const body = document.getElementById("tableBody");
        const head = document.getElementById("tableHead");
        const message = document.getElementById("listMessage");

        body.replaceChildren();
        head.replaceChildren();
        message.textContent = "Loading...";

        try {
            const response = await fetch(
                `${API_URL}/${currentResource}`
            );

            const records = await response.json();

            if (!response.ok) {
                throw new Error(records.error || "Failed to load records.");
            }

            const columns = currentResource === "anime"
                ? animeColumns
                : mangaColumns;

            const headerRow = document.createElement("tr");

            columns.forEach(([key, label]) => {
                const th = document.createElement("th");
                th.textContent = label;
                headerRow.appendChild(th);
            });

            head.appendChild(headerRow);

            if (records.length === 0) {
                message.textContent = "No records found.";
                return;
            }

            records.forEach(record => {
                const row = document.createElement("tr");

                columns.forEach(([key]) => {
                    const cell = document.createElement("td");
                    cell.textContent = record[key] ?? "-";
                    row.appendChild(cell);
                });

                body.appendChild(row);
            });

            message.textContent =
                `${records.length} record(s) found.`;

        } catch (error) {
            message.textContent =
                "Error: " + error.message
                + "\nCheck that the PHP server and database are running.";
        }
    }

    document.getElementById("recordForm")
        .addEventListener("submit", async function(event) {
            event.preventDefault();

            const form = event.target;
            const data = new FormData(form);
            const record = {};

            // Collect the fields for the selected resource.
            for (const [key, value] of data.entries()) {
                if (key === "manga_genre") {
                    if (currentResource === "manga" && value.trim()) {
                        record.genre = value.trim();
                    }
                    continue;
                }

                if (key === "genre" && currentResource === "manga") {
                    continue;
                }

                if (value.trim() === "") {
                    continue;
                }

                if (key === "year_aired"
                    || key === "number_of_episodes"
                    || key === "number_of_seasons"
                    || key === "year_published"
                    || key === "number_of_volumes"
                    || key === "number_of_chapters") {
                    record[key] = Number(value);
                } else {
                    record[key] = value.trim();
                }
            }

            const message = document.getElementById("formMessage");
            message.textContent = "Saving...";

            try {
                const response = await fetch(
                    `${API_URL}/${currentResource}`,
                    {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json"
                        },
                        body: JSON.stringify(record)
                    }
                );

                const result = await response.json();

                if (!response.ok) {
                    throw new Error(
                        result.error || "Could not add record."
                    );
                }

                message.textContent = "Record added successfully!";
                form.reset();

                await loadRecords();

            } catch (error) {
                message.textContent = "Error: " + error.message;
            }
        });

    // Display anime records when the page first opens.
    loadRecords();
</script>
</body>
</html>