# EduNexus

EduNexus is an ERP for schools, colleges and coaching centres: students, admissions, attendance, exams, fees and staff, with one deployment per client.

## Run it locally

From the `docker/` folder, start the containers, then set up the database:

```bash
docker compose up -d
docker compose exec app php artisan migrate --seed
```

The app is at http://localhost:8000 and the Vite dev server at port 5173.

## Everything else

Everything else: docs/EDUNEXUS.md
