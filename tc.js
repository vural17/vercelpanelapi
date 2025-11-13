import fetch from "node-fetch"; // Vercel Node.js'te fetch zaten var, bunu import etmeye gerek yok
export default async function handler(req, res) {
  const { tc, test } = req.query;

  if (test) {
    return res.status(200).json({
      status: true,
      message: "API çalışıyor",
      developer: "Punishe0",
      key: "hanedanfree",
      timestamp: new Date().toISOString(),
      server: process.env.SERVER_SOFTWARE || "Vercel"
    });
  }

  if (!tc || tc.length !== 11 || isNaN(tc)) {
    return res.status(400).json({
      status: false,
      message: "Geçersiz TC numarası. 11 haneli olmalı.",
      data: null
    });
  }

  try {
    // Örnek: login ve TC sorgusu
    // PHP kodundaki hanedan.liveblog365.com login mantığını fetch ile JS'e uyarlayabilirsin
    // Şu an dummy veri dönüyoruz
    const result = {
      status: true,
      message: "Sorgu başarılı",
      data: {
        tc: tc,
        ad_soyad: "Mehdi Vural",
        dogum_tarihi: "01.01.2000",
        nufus_il_ilce: "Çanakkale / Merkez",
        anne_bilgisi: "Fatma",
        baba_bilgisi: "Ersin",
        sorgu_tarihi: new Date().toISOString()
      },
      developer: "Punishe0",
      key: "hanedanfree"
    };

    return res.status(200).json(result);

  } catch (err) {
    console.error(err);
    return res.status(500).json({
      status: false,
      message: "Sorgu sırasında bir hata oluştu",
      data: null
    });
  }
      }
