export interface ToolMetadata {
  title: string;
  description: string;
  keywords: string[];
  path: string;
}

export const toolsMetadata: Record<string, ToolMetadata> = {
  whatsapp: {
    title: "WhatsApp Link Generator",
    description: "Buat link WhatsApp dengan pesan otomatis. Mudah untuk bisnis online, customer service, dan promosi. Gratis dan tanpa registrasi.",
    keywords: ["whatsapp link", "wa.me generator", "link whatsapp bisnis", "whatsapp marketing"],
    path: "/tools/whatsapp"
  },
  compress: {
    title: "Kompres Foto Online Gratis",
    description: "Kompres ukuran foto hingga 80% tanpa kehilangan kualitas. Proses 100% di browser, privat dan aman. Tidak ada upload ke server.",
    keywords: ["kompres foto", "resize gambar", "kurangi ukuran foto", "compress image online"],
    path: "/tools/compress"
  },
  pasfoto: {
    title: "Pas Foto Online - Resize 3x4, 4x6, 2x3",
    description: "Buat pas foto ukuran standar Indonesia (3x4, 4x6, 2x3) untuk KTP, SIM, SKCK, dan lamaran kerja. Gratis dan mudah digunakan.",
    keywords: ["pas foto online", "foto 3x4", "foto 4x6", "pas foto ktp", "pas foto lamaran"],
    path: "/tools/pasfoto"
  },
  pdf: {
    title: "Gabung PDF Online Gratis",
    description: "Gabungkan beberapa file PDF menjadi satu dokumen. Atur urutan halaman dengan drag & drop. Proses cepat dan privat.",
    keywords: ["gabung pdf", "merge pdf", "combine pdf online", "satukan pdf"],
    path: "/tools/pdf"
  },
  qrcode: {
    title: "QR Code Generator Gratis",
    description: "Buat QR Code untuk link, teks, atau WhatsApp. Download dalam format PNG. Gratis tanpa watermark.",
    keywords: ["qr code generator", "buat qr code", "qr code gratis", "qr code online"],
    path: "/tools/qrcode"
  },
  password: {
    title: "Password Generator - Buat Password Kuat",
    description: "Generate password acak yang kuat dan aman. Pilih panjang dan kombinasi karakter sesuai kebutuhan.",
    keywords: ["password generator", "buat password", "password acak", "password kuat"],
    path: "/tools/password"
  },
  wordcounter: {
    title: "Hitung Kata & Karakter Online",
    description: "Hitung jumlah kata, karakter, kalimat, dan paragraf dalam teks. Cocok untuk essay, artikel, dan caption sosmed.",
    keywords: ["hitung kata", "word counter", "character counter", "hitung karakter"],
    path: "/tools/word-counter"
  },
  textcase: {
    title: "Ubah Huruf Besar Kecil Online",
    description: "Konversi teks ke huruf besar, kecil, kapital, atau title case. Mudah dan cepat untuk format teks.",
    keywords: ["huruf besar", "huruf kecil", "uppercase", "lowercase", "title case"],
    path: "/tools/text-case"
  },
  json: {
    title: "JSON Formatter & Beautifier",
    description: "Format dan rapikan JSON dengan syntax highlighting. Validasi JSON dan temukan error dengan mudah.",
    keywords: ["json formatter", "json beautifier", "format json", "json validator"],
    path: "/tools/json"
  },
  lorem: {
    title: "Lorem Ipsum Generator Indonesia",
    description: "Generate teks placeholder Lorem Ipsum untuk desain dan mockup. Pilih jumlah paragraf atau kata sesuai kebutuhan.",
    keywords: ["lorem ipsum", "dummy text", "placeholder text", "teks contoh"],
    path: "/tools/lorem"
  },
  age: {
    title: "Kalkulator Umur - Hitung Usia Tepat",
    description: "Hitung umur tepat dalam tahun, bulan, dan hari dari tanggal lahir. Gratis dan akurat.",
    keywords: ["kalkulator umur", "hitung usia", "age calculator", "tanggal lahir"],
    path: "/tools/age"
  },
  unit: {
    title: "Konversi Satuan Online Lengkap",
    description: "Konversi satuan panjang, berat, suhu, dan lainnya. Kalkulator konversi lengkap dan akurat.",
    keywords: ["konversi satuan", "unit converter", "meter ke kaki", "kg ke lbs"],
    path: "/tools/unit"
  },
  color: {
    title: "Color Picker - Pilih Warna HEX RGB",
    description: "Pilih warna dan dapatkan kode HEX, RGB, dan HSL. Color picker online gratis untuk desainer.",
    keywords: ["color picker", "pilih warna", "kode warna", "hex color", "rgb color"],
    path: "/tools/color"
  },
  screenshot: {
    title: "Screenshot ke PDF Converter",
    description: "Gabungkan beberapa screenshot menjadi satu file PDF. Cocok untuk dokumentasi dan arsip chat.",
    keywords: ["screenshot to pdf", "gambar ke pdf", "convert screenshot", "image to pdf"],
    path: "/tools/screenshot-pdf"
  },
  base64: {
    title: "Image to Base64 Converter",
    description: "Konversi gambar ke kode Base64 dan sebaliknya. Berguna untuk embed gambar di HTML/CSS.",
    keywords: ["image to base64", "base64 encoder", "gambar ke base64", "base64 converter"],
    path: "/tools/base64"
  },
  invoice: {
    title: "Invoice Generator Gratis",
    description: "Buat invoice profesional untuk bisnis. Kustomisasi logo, item, dan download PDF. Gratis tanpa registrasi.",
    keywords: ["invoice generator", "buat invoice", "faktur online", "invoice gratis"],
    path: "/tools/invoice"
  },
  random: {
    title: "Kocok Arisan - Random Name Picker",
    description: "Pilih pemenang acak dari daftar nama. Cocok untuk arisan, giveaway, dan undian. Dengan animasi seru!",
    keywords: ["kocok arisan", "random picker", "pilih pemenang acak", "undian online"],
    path: "/tools/random"
  },
  terbilang: {
    title: "Terbilang - Angka ke Huruf Indonesia",
    description: "Konversi angka ke terbilang dalam Bahasa Indonesia. Untuk kwitansi, cek, dan dokumen resmi.",
    keywords: ["terbilang", "angka ke huruf", "number to words", "konversi angka"],
    path: "/tools/terbilang"
  },
  signature: {
    title: "Tanda Tangan Digital Online",
    description: "Buat tanda tangan digital dengan canvas. Download sebagai PNG transparan untuk dokumen.",
    keywords: ["tanda tangan digital", "signature pad", "ttd online", "digital signature"],
    path: "/tools/signature"
  },
  converter: {
    title: "Image Converter - WebP, PNG, JPG",
    description: "Konversi format gambar antar WebP, PNG, JPG, dan lainnya. Batch conversion dengan kualitas adjustable.",
    keywords: ["image converter", "convert webp", "ubah format gambar", "png to jpg"],
    path: "/tools/converter"
  },
  watermark: {
    title: "Watermark Foto Online",
    description: "Tambahkan watermark teks atau gambar ke foto. Lindungi karya dan branding bisnis Anda.",
    keywords: ["watermark foto", "tambah watermark", "watermark online", "protect image"],
    path: "/tools/watermark"
  },
  diff: {
    title: "Diff Checker - Bandingkan Teks",
    description: "Bandingkan dua teks dan temukan perbedaannya. Highlighting untuk additions, deletions, dan perubahan.",
    keywords: ["diff checker", "compare text", "bandingkan teks", "text difference"],
    path: "/tools/diff"
  }
};
