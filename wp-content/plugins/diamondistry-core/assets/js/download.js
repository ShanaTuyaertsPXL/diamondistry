/*
 * Download a finished painting as a branded PNG (see DiamondistryGrid.renderPoster).
 * Config comes from wp_localize_script: DiamondistryDownloadConfig { logo, siteName }.
 */
(function (global) {
  function loadImage(src) {
    return new Promise((resolve) => {
      if (!src) {
        resolve(null);
        return;
      }
      const img = new Image();
      img.onload = () => resolve(img);
      img.onerror = () => resolve(null);
      img.src = src;
    });
  }

  async function save(data, title) {
    const config = global.DiamondistryDownloadConfig || {};
    const [logo] = await Promise.all([
      loadImage(config.logo),
      document.fonts ? document.fonts.ready : Promise.resolve(),
    ]);

    const date = new Date().toLocaleDateString("en-GB", { day: "numeric", month: "long", year: "numeric" });
    const canvas = global.DiamondistryGrid.renderPoster(data, {
      logo,
      siteName: config.siteName,
      title,
      subtitle: `Completed on ${date}`,
    });

    const slug = String(title || "painting").toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-|-$/g, "");
    canvas.toBlob((blob) => {
      if (!blob) {
        return;
      }
      const link = document.createElement("a");
      link.href = URL.createObjectURL(blob);
      link.download = `diamondistry-${slug || "painting"}.png`;
      document.body.append(link);
      link.click();
      link.remove();
      setTimeout(() => URL.revokeObjectURL(link.href), 4000);
    }, "image/png");
  }

  global.DiamondistryDownload = { save };
})(window);
