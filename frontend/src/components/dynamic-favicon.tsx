"use client";

import { useEffect } from "react";
import { apiFetch } from "@/lib/api";

interface BrandingResponse {
  status: string;
  school_name: string;
  school_logo_url: string | null;
}

export function DynamicFavicon() {
  useEffect(() => {
    let cancelled = false;
    let desiredHref: string | null = null;

    function applyFavicon() {
      if (!desiredHref) return;
      let link = document.querySelector<HTMLLinkElement>("link[rel~='icon']");
      if (!link) {
        link = document.createElement("link");
        link.rel = "icon";
        document.head.appendChild(link);
      }
      if (link.href !== desiredHref) {
        link.href = desiredHref;
      }
    }

    apiFetch<BrandingResponse>("/api/settings/branding")
      .then((data) => {
        if (cancelled || !data.school_logo_url) return;
        desiredHref = data.school_logo_url;
        applyFavicon();
        if (data.school_name) {
          document.title = data.school_name;
        }
      })
      .catch(() => {
        // Keep the default static favicon/title if the API isn't reachable.
      });

    // Next.js's App Router re-inserts its own static favicon <link> on
    // client-side navigation, silently undoing the override above — so we
    // watch <head> and reapply ours whenever that happens.
    const observer = new MutationObserver(() => applyFavicon());
    observer.observe(document.head, {
      childList: true,
      subtree: true,
      attributes: true,
      attributeFilter: ["href"],
    });

    return () => {
      cancelled = true;
      observer.disconnect();
    };
  }, []);

  return null;
}
