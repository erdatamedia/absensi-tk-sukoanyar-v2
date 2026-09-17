import type { Metadata } from "next";
import { Figtree } from "next/font/google";
import "./globals.css";
import { AuthProvider } from "@/context/auth-context";

const figtree = Figtree({
  variable: "--font-sans",
  subsets: ["latin"],
  weight: ["400", "500", "600", "700"],
});

export const metadata: Metadata = {
  title: "Absensi TK",
  description: "Sistem absensi TK",
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="id" className={`${figtree.variable} h-full antialiased`}>
      <body className="app-gradient-bg min-h-full flex flex-col text-foreground">
        <AuthProvider>{children}</AuthProvider>
      </body>
    </html>
  );
}
