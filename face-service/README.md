# Face Service

Microservice pengenalan wajah untuk Absensi TK. Dipanggil server-ke-server oleh Laravel (lihat `app/Services/FaceRecognitionClient.php`) — tidak pernah diakses langsung dari browser, jadi tidak perlu CORS.

Dibangun dengan modul `face` bawaan OpenCV (`FaceDetectorYN` / YuNet untuk deteksi + `FaceRecognizerSF` / SFace untuk embedding wajah), bukan `insightface`/`dlib` — keduanya butuh kompilasi C++ dari source (`cmake`, `boost`) yang tidak tersedia/gagal build di beberapa mesin macOS dengan Xcode Command Line Tools yang tidak lengkap. `opencv-contrib-python-headless` didistribusikan sebagai wheel siap pakai, tidak perlu kompilasi apa pun.

## Setup

```bash
cd face-service
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt
```

Unduh model (sekali saja, masing-masing kecil — ~230KB & ~37MB):

```bash
mkdir -p models
curl -L -o models/face_detection_yunet.onnx \
  https://github.com/opencv/opencv_zoo/raw/main/models/face_detection_yunet/face_detection_yunet_2023mar.onnx
curl -L -o models/face_recognition_sface.onnx \
  https://github.com/opencv/opencv_zoo/raw/main/models/face_recognition_sface/face_recognition_sface_2021dec.onnx
```

## Menjalankan

```bash
source venv/bin/activate
uvicorn main:app --port 8001
```

Set `FACE_SERVICE_URL=http://127.0.0.1:8001` di `.env` Laravel (sudah ada secara default).

## Environment Variables

| Variabel | Default | Keterangan |
|---|---|---|
| `FACE_MATCH_THRESHOLD` | `0.36` | Ambang cosine-similarity (skala SFace) untuk dianggap match pada `/recognize`. Naikkan kalau terlalu banyak false-positive, turunkan kalau siswa asli sering tidak kebaca. |
| `FACE_DET_SIZE` | `640` | Ukuran input deteksi wajah (piksel). |

## Endpoints

- `GET /health` — cek service hidup.
- `POST /embed` — `{ "image": "<base64 atau data URL>" }` → `{ "embedding": [...128 float], "confidence": 0.98 }`. Dipakai saat enrollment foto referensi siswa.
- `POST /recognize` — `{ "image": "...", "candidates": [{ "id": 1, "embedding": [...] }] }` → `{ "siswa_id": 1, "score": 0.42 }` atau `{ "siswa_id": null }` kalau tidak ada yang cocok. Dipakai saat kiosk absensi.

## Deploy ke cPanel (shared hosting) via "Setup Python App"

Shared hosting tidak bisa menjalankan `uvicorn` sebagai proses yang hidup terus — dipakai Passenger lewat fitur **Setup Python App** di cPanel sebagai gantinya. FastAPI itu ASGI, Passenger butuh WSGI, jadi ada `passenger_wsgi.py` di folder ini yang menjembatani keduanya lewat `a2wsgi` (sudah diverifikasi jalan).

1. Di cPanel: **Setup Python App** → Create Application. Python version 3.9+, Application root diarahkan ke folder `face-service/` ini, Application URL misalnya `face.absensi-tk.nwsn.cc` (subdomain terpisah, dibuat dulu di **Subdomains**).
2. Upload isi folder `face-service/` (kecuali `venv/`) ke Application root lewat File Manager, **termasuk** `passenger_wsgi.py`, `main.py`, `requirements.txt`.
3. Buat folder `models/` di situ dan upload 2 file model (~37MB total) — file ini sengaja tidak ikut di git karena ukurannya. Unduh dulu di komputer lokal dari link di bagian "Setup" di atas, lalu upload lewat File Manager.
4. Di halaman Setup Python App, buka **"Enter to the virtual environment"** (command yang ditampilkan cPanel) lalu jalankan `pip install -r requirements.txt`.
5. Restart aplikasi dari tombol di Setup Python App. Cek `https://face.absensi-tk.nwsn.cc/health` harus balas `{"status":"ok"}`.
6. Set `FACE_SERVICE_URL=https://face.absensi-tk.nwsn.cc` di `.env` Laravel.

**Kalau host tidak punya fitur "Setup Python App" sama sekali**: face-service bisa dihosting terpisah di luar cPanel (mis. Render.com/Railway free tier, atau VPS kecil) — Laravel tetap bisa memanggilnya lewat HTTPS dari mana saja, `FACE_SERVICE_URL` tinggal diarahkan ke situ. Bagian lain (Laravel + Next.js) tidak perlu pindah.
