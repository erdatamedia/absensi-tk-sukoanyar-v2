export interface User {
  id: number;
  name: string;
  email: string;
  role: "admin" | "orang_tua";
}

export interface Kelas {
  id: number;
  nama_kelas: string;
  tahun_ajaran: string;
  siswa_count?: number;
}

export interface Siswa {
  id: number;
  nis: string;
  nama: string;
  kelas_id: number;
  jenis_kelamin: "L" | "P";
  tanggal_lahir: string | null;
  alamat: string | null;
  keterangan: string | null;
  qr_token: string;
  foto_referensi: string | null;
  kelas?: Kelas;
}

export interface Absensi {
  id: number;
  siswa_id: number;
  tanggal: string;
  jam_masuk: string | null;
  jam_pulang: string | null;
  foto_masuk: string | null;
  foto_pulang: string | null;
  status: "hadir" | "izin" | "sakit" | "alpha";
  keterangan: string | null;
  sumber: "scan_qr" | "manual" | "auto_alpha";
  terlambat: boolean;
  siswa?: Siswa;
}

export interface Paginated<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface MonitorSummary {
  masuk: number;
  pulang: number;
  alpha: number;
  belum_pulang: number;
}

export interface MonitorAktivitas {
  id: number;
  updated_at: string;
  nis: string;
  nama: string;
  kelas: string;
  jam_masuk: string;
  jam_pulang: string;
  status_absensi: string;
  keterangan: string;
  sumber: string;
  terlambat: boolean;
}
