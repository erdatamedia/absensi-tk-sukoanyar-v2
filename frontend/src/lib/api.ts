const API_URL = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000";

export class ApiError extends Error {
  status: number;
  data: unknown;

  constructor(status: number, data: unknown) {
    const message =
      (data as { msg?: string; message?: string } | null)?.msg ??
      (data as { msg?: string; message?: string } | null)?.message ??
      `Request failed with status ${status}`;
    super(message);
    this.status = status;
    this.data = data;
  }
}

function getCookie(name: string): string | null {
  if (typeof document === "undefined") return null;
  const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));
  return match ? decodeURIComponent(match[1]) : null;
}

let csrfReady: Promise<void> | null = null;

function ensureCsrfCookie(): Promise<void> {
  if (getCookie("XSRF-TOKEN")) return Promise.resolve();
  if (!csrfReady) {
    csrfReady = fetch(`${API_URL}/sanctum/csrf-cookie`, {
      credentials: "include",
    })
      .then(() => undefined)
      .finally(() => {
        csrfReady = null;
      });
  }
  return csrfReady;
}

export async function apiFetch<T = unknown>(
  path: string,
  init: RequestInit = {}
): Promise<T> {
  const method = (init.method ?? "GET").toUpperCase();
  if (method !== "GET" && method !== "HEAD") {
    await ensureCsrfCookie();
  }

  const headers = new Headers(init.headers);
  headers.set("Accept", "application/json");
  if (init.body && !(init.body instanceof FormData)) {
    headers.set("Content-Type", "application/json");
  }
  const xsrf = getCookie("XSRF-TOKEN");
  if (xsrf) headers.set("X-XSRF-TOKEN", xsrf);

  const res = await fetch(`${API_URL}${path}`, {
    ...init,
    headers,
    credentials: "include",
  });

  if (res.status === 204) {
    return undefined as T;
  }

  const contentType = res.headers.get("content-type") ?? "";
  const data = contentType.includes("application/json")
    ? await res.json().catch(() => null)
    : null;

  if (!res.ok) {
    throw new ApiError(res.status, data);
  }

  return data as T;
}

export function apiFileUrl(path: string): string {
  return `${API_URL}${path}`;
}

export async function login(email: string, password: string): Promise<void> {
  await ensureCsrfCookie();
  // Laravel's session login redirects (302) on both success and failure.
  // That redirect target isn't CORS-enabled, so we must not let the browser
  // follow it — we submit with redirect: "manual" and verify the outcome
  // afterwards via GET /api/user (which is CORS-safe JSON).
  const headers = new Headers({
    Accept: "application/json",
    "Content-Type": "application/json",
  });
  const xsrf = getCookie("XSRF-TOKEN");
  if (xsrf) headers.set("X-XSRF-TOKEN", xsrf);

  await fetch(`${API_URL}/login`, {
    method: "POST",
    credentials: "include",
    redirect: "manual",
    headers,
    body: JSON.stringify({ email, password }),
  });
}

export async function loginParent(phone: string, pin: string): Promise<void> {
  await ensureCsrfCookie();
  // Unlike /login, this is our own JSON endpoint (no Breeze redirect), so
  // apiFetch's normal flow works fine here.
  await apiFetch("/parent-login", {
    method: "POST",
    body: JSON.stringify({ phone, pin }),
  });
}

export async function logout(): Promise<void> {
  // Same redirect-on-success issue as login() — Laravel redirects to "/" after
  // logout, which isn't CORS-enabled, so avoid following it.
  const headers = new Headers({ Accept: "application/json" });
  const xsrf = getCookie("XSRF-TOKEN");
  if (xsrf) headers.set("X-XSRF-TOKEN", xsrf);

  await fetch(`${API_URL}/logout`, {
    method: "POST",
    credentials: "include",
    redirect: "manual",
    headers,
  });
}

export { API_URL };
