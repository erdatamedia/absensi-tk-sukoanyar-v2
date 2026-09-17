import type { NextConfig } from "next";
import path from "node:path";

const nextConfig: NextConfig = {
  // Everything in this app is a client component talking to the Laravel API —
  // no route handlers, server actions, or dynamic segments — so a static
  // export works and is the easiest way to host this on cPanel (plain
  // static files, no Node.js process required).
  output: "export",
  images: {
    unoptimized: true,
  },
  turbopack: {
    root: path.join(__dirname),
  },
};

export default nextConfig;
