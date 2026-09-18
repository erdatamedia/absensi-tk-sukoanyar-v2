import type { Metadata } from "next";
import { Figtree } from "next/font/google";
import "./globals.css";
import { AuthProvider } from "@/context/auth-context";
import { DynamicFavicon } from "@/components/dynamic-favicon";

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
        <DynamicFavicon />
        <AuthProvider>{children}</AuthProvider>
      </body>
    </html>
  );
}
