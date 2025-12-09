<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Handyreparatur Lauingen | Erepairshop - Schnell & Professionell</title>
    <meta name="description" content="Professionelle Handyreparatur in Lauingen. Display, Akku, Wasserschaden - Express Reparatur am selben Tag. iPhone, Samsung, Huawei & mehr. Jetzt kontaktieren!">
    <meta name="keywords" content="Handyreparatur Lauingen, iPhone Reparatur, Samsung Reparatur, Display Reparatur, Akku Wechsel, Smartphone Reparatur">

    <!-- Open Graph -->
    <meta property="og:title" content="Erepairshop - Handyreparatur in Lauingen">
    <meta property="og:description" content="Schnelle & professionelle Handyreparatur. Express Service am selben Tag!">
    <meta property="og:image" content="https://www.erepairshop.de/og-image.jpg">
    <meta property="og:url" content="https://www.erepairshop.de">
    <meta property="og:type" content="website">

    <!-- Structured Data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "LocalBusiness",
        "name": "Erepairshop",
        "image": "https://www.erepairshop.de/logo.jpg",
        "description": "Professionelle Handyreparatur in Lauingen",
        "address": {
            "@type": "PostalAddress",
            "streetAddress": "Siedlungsring 51",
            "addressLocality": "Lauingen",
            "postalCode": "89415",
            "addressCountry": "DE"
        },
        "geo": {
            "@type": "GeoCoordinates",
            "latitude": 48.5697,
            "longitude": 10.4285
        },
        "telephone": "+49 176 98479520",
        "email": "info@erepairshop.de",
        "url": "https://www.erepairshop.de",
        "openingHoursSpecification": [
            {
                "@type": "OpeningHoursSpecification",
                "dayOfWeek": ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"],
                "opens": "10:00",
                "closes": "18:00"
            },
            {
                "@type": "OpeningHoursSpecification",
                "dayOfWeek": "Saturday",
                "opens": "10:00",
                "closes": "16:00"
            }
        ],
        "priceRange": "$$",
        "aggregateRating": {
            "@type": "AggregateRating",
            "ratingValue": "4.9",
            "reviewCount": "127"
        }
    }
    </script>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#0066FF',
                        secondary: '#00D4AA',
                        dark: '#0a0a0a'
                    }
                }
            }
        }
    </script>

    <!-- Remix Icons -->
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">

    <style>
        html { scroll-behavior: smooth; }
        .gradient-bg { background: linear-gradient(135deg, #0066FF 0%, #00D4AA 100%); }
        .gradient-text { background: linear-gradient(135deg, #0066FF 0%, #00D4AA 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .glass { background: rgba(255,255,255,0.1); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.2); }
        .float-animation { animation: float 3s ease-in-out infinite; }
        @keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
        .pulse-green { animation: pulse-green 2s infinite; }
        @keyframes pulse-green { 0%, 100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); } 70% { box-shadow: 0 0 0 10px rgba(34, 197, 94, 0); } }
    </style>
</head>
<body class="bg-dark text-white">

    <!-- Navigation -->
    <nav class="fixed top-0 left-0 right-0 z-50 glass">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <a href="#" class="flex items-center gap-2">
                    <div class="w-10 h-10 gradient-bg rounded-xl flex items-center justify-center font-bold text-lg">E</div>
                    <span class="font-bold text-xl hidden sm:block">Erepairshop</span>
                </a>
                <div class="hidden md:flex items-center gap-8">
                    <a href="#services" class="hover:text-primary transition">Leistungen</a>
                    <a href="#about" class="hover:text-primary transition">Uber uns</a>
                    <a href="#reviews" class="hover:text-primary transition">Bewertungen</a>
                    <a href="#faq" class="hover:text-primary transition">FAQ</a>
                    <a href="#contact" class="hover:text-primary transition">Kontakt</a>
                </div>
                <a href="tel:+4917698479520" class="gradient-bg px-4 py-2 rounded-full font-semibold hover:opacity-90 transition flex items-center gap-2">
                    <i class="ri-phone-fill"></i>
                    <span class="hidden sm:inline">Anrufen</span>
                </a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="min-h-screen flex items-center justify-center relative overflow-hidden pt-16">
        <!-- Background Effects -->
        <div class="absolute inset-0 overflow-hidden">
            <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-primary/20 rounded-full blur-3xl"></div>
            <div class="absolute bottom-1/4 right-1/4 w-96 h-96 bg-secondary/20 rounded-full blur-3xl"></div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="text-center">
                <!-- Status Badge -->
                <div class="inline-flex items-center gap-2 glass px-4 py-2 rounded-full mb-8" id="statusBadge">
                    <span class="w-3 h-3 bg-green-500 rounded-full pulse-green"></span>
                    <span class="text-sm">Jetzt geoffnet</span>
                </div>

                <h1 class="text-4xl sm:text-6xl lg:text-7xl font-bold mb-6">
                    Handy kaputt?<br>
                    <span class="gradient-text">Wir reparieren es!</span>
                </h1>

                <p class="text-xl text-gray-400 mb-8 max-w-2xl mx-auto">
                    Professionelle Smartphone-Reparatur in Lauingen.
                    Express-Service am selben Tag. Alle Marken & Modelle.
                </p>

                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="https://wa.me/4917698479520?text=Hallo,%20ich%20brauche%20eine%20Handy-Reparatur"
                       target="_blank"
                       class="inline-flex items-center justify-center gap-2 bg-green-500 hover:bg-green-600 px-8 py-4 rounded-full font-semibold text-lg transition transform hover:scale-105">
                        <i class="ri-whatsapp-fill text-2xl"></i>
                        WhatsApp schreiben
                    </a>
                    <a href="#contact"
                       class="inline-flex items-center justify-center gap-2 glass hover:bg-white/20 px-8 py-4 rounded-full font-semibold text-lg transition">
                        <i class="ri-mail-fill"></i>
                        Kontaktformular
                    </a>
                </div>

                <!-- Trust Badges -->
                <div class="flex flex-wrap justify-center gap-8 mt-12 text-gray-400">
                    <div class="flex items-center gap-2">
                        <i class="ri-shield-check-fill text-secondary text-xl"></i>
                        <span>Garantie auf alle Reparaturen</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <i class="ri-time-fill text-secondary text-xl"></i>
                        <span>Express am selben Tag</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <i class="ri-star-fill text-yellow-400 text-xl"></i>
                        <span>4.9/5 Google Bewertung</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Scroll Indicator -->
        <div class="absolute bottom-8 left-1/2 -translate-x-1/2 float-animation">
            <i class="ri-arrow-down-line text-3xl text-gray-500"></i>
        </div>
    </section>

    <!-- Services Section -->
    <section id="services" class="py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl sm:text-4xl font-bold mb-4">Unsere <span class="gradient-text">Leistungen</span></h2>
                <p class="text-gray-400 max-w-2xl mx-auto">Von Display-Reparatur bis Wasserschaden - wir bringen Ihr Gerat wieder zum Laufen</p>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Service Cards -->
                <div class="glass rounded-2xl p-6 hover:bg-white/10 transition group">
                    <div class="w-14 h-14 gradient-bg rounded-xl flex items-center justify-center mb-4 group-hover:scale-110 transition">
                        <i class="ri-smartphone-line text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Display Reparatur</h3>
                    <p class="text-gray-400">Gebrochenes oder defektes Display? Wir tauschen es schnell und zuverlassig aus.</p>
                </div>

                <div class="glass rounded-2xl p-6 hover:bg-white/10 transition group">
                    <div class="w-14 h-14 gradient-bg rounded-xl flex items-center justify-center mb-4 group-hover:scale-110 transition">
                        <i class="ri-battery-2-charge-line text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Akku Wechsel</h3>
                    <p class="text-gray-400">Akku halt nicht mehr? Neuer Original-Akku fur langere Laufzeit.</p>
                </div>

                <div class="glass rounded-2xl p-6 hover:bg-white/10 transition group">
                    <div class="w-14 h-14 gradient-bg rounded-xl flex items-center justify-center mb-4 group-hover:scale-110 transition">
                        <i class="ri-drop-line text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Wasserschaden</h3>
                    <p class="text-gray-400">Handy ins Wasser gefallen? Schnelle Hilfe kann Ihr Gerat retten!</p>
                </div>

                <div class="glass rounded-2xl p-6 hover:bg-white/10 transition group">
                    <div class="w-14 h-14 gradient-bg rounded-xl flex items-center justify-center mb-4 group-hover:scale-110 transition">
                        <i class="ri-camera-line text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Kamera Reparatur</h3>
                    <p class="text-gray-400">Front- oder Hauptkamera defekt? Wir reparieren beide Kameras.</p>
                </div>

                <div class="glass rounded-2xl p-6 hover:bg-white/10 transition group">
                    <div class="w-14 h-14 gradient-bg rounded-xl flex items-center justify-center mb-4 group-hover:scale-110 transition">
                        <i class="ri-volume-up-line text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Lautsprecher & Mikrofon</h3>
                    <p class="text-gray-400">Tonprobleme? Lautsprecher und Mikrofon werden ausgetauscht.</p>
                </div>

                <div class="glass rounded-2xl p-6 hover:bg-white/10 transition group">
                    <div class="w-14 h-14 gradient-bg rounded-xl flex items-center justify-center mb-4 group-hover:scale-110 transition">
                        <i class="ri-plug-line text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Ladebuchse</h3>
                    <p class="text-gray-400">Handy ladt nicht mehr richtig? Neue Ladebuchse lost das Problem.</p>
                </div>
            </div>

            <!-- Brands -->
            <div class="mt-16 text-center">
                <p class="text-gray-500 mb-6">Wir reparieren alle Marken</p>
                <div class="flex flex-wrap justify-center gap-8 text-3xl text-gray-600">
                    <i class="ri-apple-fill hover:text-white transition cursor-pointer" title="iPhone"></i>
                    <span class="font-bold hover:text-white transition cursor-pointer">Samsung</span>
                    <span class="font-bold hover:text-white transition cursor-pointer">Huawei</span>
                    <span class="font-bold hover:text-white transition cursor-pointer">Xiaomi</span>
                </div>
            </div>
        </div>
    </section>

    <!-- About / Why Us Section -->
    <section id="about" class="py-20 relative">
        <div class="absolute inset-0 gradient-bg opacity-10"></div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <div>
                    <h2 class="text-3xl sm:text-4xl font-bold mb-6">Warum <span class="gradient-text">Erepairshop?</span></h2>
                    <p class="text-gray-400 mb-8 text-lg">
                        Mit jahrelanger Erfahrung und Leidenschaft fur Technik bieten wir Ihnen den besten Service in Lauingen und Umgebung.
                    </p>

                    <div class="space-y-4">
                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 gradient-bg rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="ri-speed-fill"></i>
                            </div>
                            <div>
                                <h3 class="font-bold mb-1">Express Reparatur</h3>
                                <p class="text-gray-400">Die meisten Reparaturen erledigen wir noch am selben Tag.</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 gradient-bg rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="ri-shield-star-fill"></i>
                            </div>
                            <div>
                                <h3 class="font-bold mb-1">Qualitatsgarantie</h3>
                                <p class="text-gray-400">Hochwertige Ersatzteile und Garantie auf alle Reparaturen.</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 gradient-bg rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="ri-money-euro-circle-fill"></i>
                            </div>
                            <div>
                                <h3 class="font-bold mb-1">Faire Preise</h3>
                                <p class="text-gray-400">Transparente Preise ohne versteckte Kosten.</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 gradient-bg rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="ri-user-heart-fill"></i>
                            </div>
                            <div>
                                <h3 class="font-bold mb-1">Personliche Beratung</h3>
                                <p class="text-gray-400">Freundlicher Service und ehrliche Beratung.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Opening Hours -->
                <div class="glass rounded-2xl p-8">
                    <h3 class="text-2xl font-bold mb-6 flex items-center gap-3">
                        <i class="ri-time-fill text-primary"></i>
                        Offnungszeiten
                    </h3>

                    <div class="space-y-3" id="openingHours">
                        <div class="flex justify-between items-center py-2 border-b border-white/10">
                            <span>Montag - Freitag</span>
                            <span class="font-semibold">10:00 - 18:00</span>
                        </div>
                        <div class="flex justify-between items-center py-2 border-b border-white/10">
                            <span>Samstag</span>
                            <span class="font-semibold">10:00 - 16:00</span>
                        </div>
                        <div class="flex justify-between items-center py-2">
                            <span>Sonntag</span>
                            <span class="text-red-400">Geschlossen</span>
                        </div>
                    </div>

                    <div class="mt-6 p-4 rounded-xl" id="currentStatus">
                        <!-- Filled by JS -->
                    </div>

                    <div class="mt-6 pt-6 border-t border-white/10">
                        <div class="flex items-start gap-3">
                            <i class="ri-map-pin-fill text-primary text-xl"></i>
                            <div>
                                <p class="font-semibold">Erepairshop</p>
                                <p class="text-gray-400">Siedlungsring 51</p>
                                <p class="text-gray-400">89415 Lauingen</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- PunktePass Section -->
    <section id="punktepass" class="py-20 relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-r from-purple-600/20 to-pink-600/20"></div>
        <div class="absolute top-0 right-0 w-96 h-96 bg-purple-500/30 rounded-full blur-3xl"></div>
        <div class="absolute bottom-0 left-0 w-96 h-96 bg-pink-500/30 rounded-full blur-3xl"></div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <div>
                    <div class="inline-flex items-center gap-2 bg-purple-500/20 border border-purple-500/30 px-4 py-2 rounded-full mb-6">
                        <i class="ri-gift-fill text-purple-400"></i>
                        <span class="text-purple-300 font-medium">NEU: Treueprogramm</span>
                    </div>

                    <h2 class="text-3xl sm:text-4xl font-bold mb-6">
                        Punkte sammeln mit<br>
                        <span class="bg-gradient-to-r from-purple-400 to-pink-400 bg-clip-text text-transparent">PunktePass</span>
                    </h2>

                    <p class="text-xl text-gray-300 mb-8">
                        Jetzt bei jeder Reparatur Punkte sammeln und bei Ihrem nachsten Besuch sparen!
                    </p>

                    <div class="space-y-4 mb-8">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-purple-500/20 rounded-xl flex items-center justify-center">
                                <i class="ri-smartphone-fill text-purple-400 text-xl"></i>
                            </div>
                            <div>
                                <p class="font-semibold">App herunterladen</p>
                                <p class="text-gray-400 text-sm">Kostenlos fur iOS und Android</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-purple-500/20 rounded-xl flex items-center justify-center">
                                <i class="ri-qr-scan-2-line text-purple-400 text-xl"></i>
                            </div>
                            <div>
                                <p class="font-semibold">QR-Code scannen</p>
                                <p class="text-gray-400 text-sm">Nach jeder Reparatur Punkte sammeln</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-green-500/20 rounded-xl flex items-center justify-center">
                                <i class="ri-money-euro-circle-fill text-green-400 text-xl"></i>
                            </div>
                            <div>
                                <p class="font-semibold">10€ Rabatt</p>
                                <p class="text-gray-400 text-sm">Schon beim 2. Besuch einlosen!</p>
                            </div>
                        </div>
                    </div>

                    <a href="https://punktepass.de" target="_blank"
                       class="inline-flex items-center gap-2 bg-gradient-to-r from-purple-500 to-pink-500 px-8 py-4 rounded-full font-semibold text-lg hover:opacity-90 transition transform hover:scale-105">
                        <i class="ri-download-2-fill"></i>
                        PunktePass App herunterladen
                    </a>
                </div>

                <div class="flex justify-center">
                    <div class="relative">
                        <!-- Decorative Phone Frame -->
                        <div class="w-64 h-[500px] bg-gradient-to-b from-purple-500/20 to-pink-500/20 rounded-[3rem] border-4 border-white/10 p-3 shadow-2xl">
                            <div class="w-full h-full bg-dark rounded-[2.5rem] flex flex-col items-center justify-center p-6 text-center">
                                <div class="w-20 h-20 bg-gradient-to-r from-purple-500 to-pink-500 rounded-2xl flex items-center justify-center mb-6">
                                    <span class="text-3xl font-bold">PP</span>
                                </div>
                                <h3 class="text-2xl font-bold mb-2">PunktePass</h3>
                                <p class="text-gray-400 mb-6">Sammle Punkte bei jedem Einkauf</p>
                                <div class="glass rounded-xl p-4 w-full mb-4">
                                    <p class="text-sm text-gray-400">Ihre Punkte</p>
                                    <p class="text-3xl font-bold gradient-text">250</p>
                                </div>
                                <div class="glass rounded-xl p-4 w-full">
                                    <p class="text-sm text-gray-400">Nachster Rabatt</p>
                                    <p class="text-xl font-bold text-green-400">10€ bei 500 Punkten</p>
                                </div>
                            </div>
                        </div>

                        <!-- Floating Elements -->
                        <div class="absolute -top-4 -right-4 glass px-4 py-2 rounded-full float-animation">
                            <span class="text-green-400 font-bold">+50 Punkte</span>
                        </div>
                        <div class="absolute -bottom-4 -left-4 glass px-4 py-2 rounded-full float-animation" style="animation-delay: 1s;">
                            <span class="text-purple-400 font-bold">2. Besuch = 10€</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Reviews Section -->
    <section id="reviews" class="py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl sm:text-4xl font-bold mb-4">Das sagen unsere <span class="gradient-text">Kunden</span></h2>
                <div class="flex items-center justify-center gap-2 mb-4">
                    <div class="flex text-yellow-400">
                        <i class="ri-star-fill"></i>
                        <i class="ri-star-fill"></i>
                        <i class="ri-star-fill"></i>
                        <i class="ri-star-fill"></i>
                        <i class="ri-star-fill"></i>
                    </div>
                    <span class="font-bold">4.9</span>
                    <span class="text-gray-400">basierend auf 127+ Bewertungen</span>
                </div>
            </div>

            <div class="grid md:grid-cols-3 gap-6">
                <div class="glass rounded-2xl p-6">
                    <div class="flex text-yellow-400 mb-4">
                        <i class="ri-star-fill"></i>
                        <i class="ri-star-fill"></i>
                        <i class="ri-star-fill"></i>
                        <i class="ri-star-fill"></i>
                        <i class="ri-star-fill"></i>
                    </div>
                    <p class="text-gray-300 mb-4">"Super schneller Service! Mein iPhone Display wurde in nur 30 Minuten repariert. Sehr freundlich und professionell. Absolute Empfehlung!"</p>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 gradient-bg rounded-full flex items-center justify-center font-bold">M</div>
                        <div>
                            <p class="font-semibold">Maria S.</p>
                            <p class="text-sm text-gray-500">vor 2 Wochen</p>
                        </div>
                    </div>
                </div>

                <div class="glass rounded-2xl p-6">
                    <div class="flex text-yellow-400 mb-4">
                        <i class="ri-star-fill"></i>
                        <i class="ri-star-fill"></i>
                        <i class="ri-star-fill"></i>
                        <i class="ri-star-fill"></i>
                        <i class="ri-star-fill"></i>
                    </div>
                    <p class="text-gray-300 mb-4">"Mein Samsung hatte einen Wasserschaden und ich dachte es sei verloren. Erepairshop hat es gerettet! Alle Daten noch da. Danke!"</p>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 gradient-bg rounded-full flex items-center justify-center font-bold">J</div>
                        <div>
                            <p class="font-semibold">Jonas K.</p>
                            <p class="text-sm text-gray-500">vor 1 Monat</p>
                        </div>
                    </div>
                </div>

                <div class="glass rounded-2xl p-6">
                    <div class="flex text-yellow-400 mb-4">
                        <i class="ri-star-fill"></i>
                        <i class="ri-star-fill"></i>
                        <i class="ri-star-fill"></i>
                        <i class="ri-star-fill"></i>
                        <i class="ri-star-fill"></i>
                    </div>
                    <p class="text-gray-300 mb-4">"Faire Preise und ehrliche Beratung. Wurde nicht zu unnötigen Reparaturen überredet. Akku-Wechsel lief perfekt. Gerne wieder!"</p>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 gradient-bg rounded-full flex items-center justify-center font-bold">L</div>
                        <div>
                            <p class="font-semibold">Lisa M.</p>
                            <p class="text-sm text-gray-500">vor 3 Wochen</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Google Review CTA -->
            <div class="text-center mt-12">
                <a href="https://g.page/r/CRI2bxlc2Rx3EBM/review"
                   target="_blank"
                   class="inline-flex items-center gap-3 glass hover:bg-white/20 px-8 py-4 rounded-full font-semibold transition">
                    <img src="https://www.google.com/favicon.ico" alt="Google" class="w-6 h-6">
                    Bewerten Sie uns auf Google
                    <i class="ri-external-link-line"></i>
                </a>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section id="faq" class="py-20 relative">
        <div class="absolute inset-0 gradient-bg opacity-5"></div>
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 relative">
            <div class="text-center mb-16">
                <h2 class="text-3xl sm:text-4xl font-bold mb-4">Haufig gestellte <span class="gradient-text">Fragen</span></h2>
            </div>

            <div class="space-y-4">
                <div class="glass rounded-xl overflow-hidden">
                    <button class="faq-toggle w-full px-6 py-4 text-left font-semibold flex justify-between items-center" onclick="toggleFaq(this)">
                        <span>Wie lange dauert eine Display-Reparatur?</span>
                        <i class="ri-add-line text-xl transition-transform"></i>
                    </button>
                    <div class="faq-content hidden px-6 pb-4 text-gray-400">
                        <p>Die meisten Display-Reparaturen dauern zwischen 30 Minuten und 2 Stunden, abhangig vom Gerat. Bei Verfugbarkeit der Ersatzteile konnen wir die Reparatur oft noch am selben Tag durchfuhren.</p>
                    </div>
                </div>

                <div class="glass rounded-xl overflow-hidden">
                    <button class="faq-toggle w-full px-6 py-4 text-left font-semibold flex justify-between items-center" onclick="toggleFaq(this)">
                        <span>Gibt es Garantie auf die Reparatur?</span>
                        <i class="ri-add-line text-xl transition-transform"></i>
                    </button>
                    <div class="faq-content hidden px-6 pb-4 text-gray-400">
                        <p>Ja! Wir geben auf alle Reparaturen eine Garantie. Die genaue Garantiedauer hangt von der Art der Reparatur ab und wird Ihnen vor der Reparatur mitgeteilt.</p>
                    </div>
                </div>

                <div class="glass rounded-xl overflow-hidden">
                    <button class="faq-toggle w-full px-6 py-4 text-left font-semibold flex justify-between items-center" onclick="toggleFaq(this)">
                        <span>Brauche ich einen Termin?</span>
                        <i class="ri-add-line text-xl transition-transform"></i>
                    </button>
                    <div class="faq-content hidden px-6 pb-4 text-gray-400">
                        <p>Nein, Sie konnen gerne ohne Termin wahrend unserer Offnungszeiten vorbeikommen. Fur eine schnellere Bearbeitung empfehlen wir jedoch, uns vorher kurz per WhatsApp oder Telefon zu kontaktieren.</p>
                    </div>
                </div>

                <div class="glass rounded-xl overflow-hidden">
                    <button class="faq-toggle w-full px-6 py-4 text-left font-semibold flex justify-between items-center" onclick="toggleFaq(this)">
                        <span>Bleiben meine Daten erhalten?</span>
                        <i class="ri-add-line text-xl transition-transform"></i>
                    </button>
                    <div class="faq-content hidden px-6 pb-4 text-gray-400">
                        <p>Bei den meisten Reparaturen (Display, Akku, etc.) bleiben Ihre Daten vollstandig erhalten. Wir empfehlen dennoch, vor der Reparatur ein Backup zu erstellen. Bei Wasserschaden kann dies je nach Schwere variieren.</p>
                    </div>
                </div>

                <div class="glass rounded-xl overflow-hidden">
                    <button class="faq-toggle w-full px-6 py-4 text-left font-semibold flex justify-between items-center" onclick="toggleFaq(this)">
                        <span>Welche Zahlungsmethoden akzeptieren Sie?</span>
                        <i class="ri-add-line text-xl transition-transform"></i>
                    </button>
                    <div class="faq-content hidden px-6 pb-4 text-gray-400">
                        <p>Wir akzeptieren Barzahlung, EC-Karte und Kreditkarten. Die Zahlung erfolgt nach erfolgreicher Reparatur.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl sm:text-4xl font-bold mb-4">Kontaktieren Sie <span class="gradient-text">uns</span></h2>
                <p class="text-gray-400">Wir freuen uns auf Ihre Nachricht!</p>
            </div>

            <div class="grid lg:grid-cols-2 gap-12">
                <!-- Contact Form -->
                <div class="glass rounded-2xl p-8">
                    <form action="contact.php" method="post" enctype="multipart/form-data" class="space-y-6">
                        <div>
                            <label for="name" class="block text-sm font-medium mb-2">Ihr Name *</label>
                            <input type="text" id="name" name="name" required
                                   class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl focus:border-primary focus:outline-none transition">
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-medium mb-2">Ihre E-Mail *</label>
                            <input type="email" id="email" name="email" required
                                   class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl focus:border-primary focus:outline-none transition">
                        </div>

                        <div>
                            <label for="phoneModel" class="block text-sm font-medium mb-2">Ihr Handymodell *</label>
                            <input type="text" id="phoneModel" name="phoneModel" placeholder="z.B. iPhone 14 Pro" required
                                   class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl focus:border-primary focus:outline-none transition">
                        </div>

                        <div>
                            <label for="message" class="block text-sm font-medium mb-2">Ihre Nachricht *</label>
                            <textarea id="message" name="message" rows="4" required
                                      class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl focus:border-primary focus:outline-none transition resize-none"></textarea>
                        </div>

                        <div>
                            <label for="fileUpload" class="block text-sm font-medium mb-2">Foto des Schadens (optional)</label>
                            <input type="file" id="fileUpload" name="fileUpload" accept="image/*"
                                   class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl focus:border-primary focus:outline-none transition file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-primary file:text-white file:cursor-pointer">
                        </div>

                        <button type="submit"
                                class="w-full gradient-bg py-4 rounded-xl font-semibold text-lg hover:opacity-90 transition flex items-center justify-center gap-2">
                            <i class="ri-send-plane-fill"></i>
                            Nachricht senden
                        </button>
                    </form>
                </div>

                <!-- Map & Info -->
                <div class="space-y-6">
                    <!-- Map -->
                    <div class="glass rounded-2xl overflow-hidden h-80">
                        <iframe
                            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2654.8!2d10.4285!3d48.5697!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x479e9f5a3e8b4a5d%3A0x1234567890abcdef!2sSiedlungsring%2051%2C%2089415%20Lauingen%20(Donau)!5e0!3m2!1sde!2sde!4v1702300000000"
                            width="100%"
                            height="100%"
                            style="border:0;"
                            allowfullscreen=""
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade">
                        </iframe>
                    </div>

                    <!-- Contact Info Cards -->
                    <div class="grid sm:grid-cols-2 gap-4">
                        <a href="tel:+4917698479520" class="glass rounded-xl p-4 hover:bg-white/10 transition flex items-center gap-4">
                            <div class="w-12 h-12 gradient-bg rounded-xl flex items-center justify-center">
                                <i class="ri-phone-fill text-xl"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-400">Telefon</p>
                                <p class="font-semibold">0176 98479520</p>
                            </div>
                        </a>

                        <a href="mailto:info@erepairshop.de" class="glass rounded-xl p-4 hover:bg-white/10 transition flex items-center gap-4">
                            <div class="w-12 h-12 gradient-bg rounded-xl flex items-center justify-center">
                                <i class="ri-mail-fill text-xl"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-400">E-Mail</p>
                                <p class="font-semibold">info@erepairshop.de</p>
                            </div>
                        </a>

                        <a href="https://wa.me/4917698479520" target="_blank" class="glass rounded-xl p-4 hover:bg-white/10 transition flex items-center gap-4">
                            <div class="w-12 h-12 bg-green-500 rounded-xl flex items-center justify-center">
                                <i class="ri-whatsapp-fill text-xl"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-400">WhatsApp</p>
                                <p class="font-semibold">Jetzt schreiben</p>
                            </div>
                        </a>

                        <a href="https://maps.google.com/?q=Siedlungsring+51,+89415+Lauingen" target="_blank" class="glass rounded-xl p-4 hover:bg-white/10 transition flex items-center gap-4">
                            <div class="w-12 h-12 gradient-bg rounded-xl flex items-center justify-center">
                                <i class="ri-map-pin-fill text-xl"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-400">Adresse</p>
                                <p class="font-semibold">Route planen</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="py-12 border-t border-white/10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid md:grid-cols-4 gap-8 mb-8">
                <div>
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-10 h-10 gradient-bg rounded-xl flex items-center justify-center font-bold text-lg">E</div>
                        <span class="font-bold text-xl">Erepairshop</span>
                    </div>
                    <p class="text-gray-400 text-sm">Professionelle Handyreparatur in Lauingen. Schnell, zuverlassig und fair.</p>
                </div>

                <div>
                    <h4 class="font-bold mb-4">Leistungen</h4>
                    <ul class="space-y-2 text-gray-400 text-sm">
                        <li>Display Reparatur</li>
                        <li>Akku Wechsel</li>
                        <li>Wasserschaden</li>
                        <li>Kamera Reparatur</li>
                    </ul>
                </div>

                <div>
                    <h4 class="font-bold mb-4">Rechtliches</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="impressum.php" class="text-gray-400 hover:text-white transition">Impressum</a></li>
                        <li><a href="datenschutz.php" class="text-gray-400 hover:text-white transition">Datenschutz</a></li>
                        <li><a href="agb.php" class="text-gray-400 hover:text-white transition">AGB</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="font-bold mb-4">Kontakt</h4>
                    <ul class="space-y-2 text-gray-400 text-sm">
                        <li>Siedlungsring 51</li>
                        <li>89415 Lauingen</li>
                        <li>0176 98479520</li>
                        <li>info@erepairshop.de</li>
                    </ul>
                </div>
            </div>

            <div class="border-t border-white/10 pt-8 flex flex-col md:flex-row justify-between items-center gap-4">
                <p class="text-gray-500 text-sm">&copy; 2024 Erepairshop. Alle Rechte vorbehalten.</p>
                <div class="flex gap-4">
                    <a href="#" class="text-gray-400 hover:text-white transition"><i class="ri-facebook-fill text-xl"></i></a>
                    <a href="#" class="text-gray-400 hover:text-white transition"><i class="ri-instagram-fill text-xl"></i></a>
                    <a href="#" class="text-gray-400 hover:text-white transition"><i class="ri-google-fill text-xl"></i></a>
                </div>
            </div>
        </div>
    </footer>

    <!-- WhatsApp Floating Button -->
    <a href="https://wa.me/4917698479520?text=Hallo,%20ich%20brauche%20eine%20Handy-Reparatur"
       target="_blank"
       class="fixed bottom-6 right-6 w-16 h-16 bg-green-500 rounded-full flex items-center justify-center shadow-lg hover:scale-110 transition z-50 group">
        <i class="ri-whatsapp-fill text-3xl"></i>
        <span class="absolute right-full mr-4 bg-white text-gray-800 px-4 py-2 rounded-lg shadow-lg text-sm font-medium whitespace-nowrap opacity-0 group-hover:opacity-100 transition">
            Schreiben Sie uns!
        </span>
    </a>

    <!-- Cookie Banner -->
    <div id="cookieBanner" class="fixed bottom-0 left-0 right-0 glass p-4 z-40 hidden">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4">
            <p class="text-sm text-gray-300">
                <i class="ri-cookie-fill mr-2"></i>
                Wir verwenden Cookies, um Ihnen das beste Erlebnis auf unserer Website zu bieten.
                <a href="datenschutz.php" class="underline hover:text-white">Mehr erfahren</a>
            </p>
            <div class="flex gap-3">
                <button onclick="acceptCookies()" class="gradient-bg px-6 py-2 rounded-full font-semibold text-sm hover:opacity-90 transition">
                    Akzeptieren
                </button>
                <button onclick="declineCookies()" class="glass px-6 py-2 rounded-full font-semibold text-sm hover:bg-white/20 transition">
                    Ablehnen
                </button>
            </div>
        </div>
    </div>

    <script>
        // Opening Hours Status
        function updateOpenStatus() {
            const now = new Date();
            const day = now.getDay(); // 0 = Sunday
            const hour = now.getHours();
            const minute = now.getMinutes();
            const time = hour + minute / 60;

            let isOpen = false;
            let statusText = '';
            let nextOpen = '';

            if (day >= 1 && day <= 5) { // Monday - Friday
                if (time >= 10 && time < 18) {
                    isOpen = true;
                    statusText = 'Jetzt geoffnet';
                    const closeIn = Math.floor((18 - time) * 60);
                    if (closeIn <= 60) {
                        statusText += ` - schliesst in ${closeIn} Min.`;
                    }
                } else if (time < 10) {
                    statusText = 'Heute geoffnet ab 10:00 Uhr';
                } else {
                    statusText = 'Morgen wieder geoffnet ab 10:00 Uhr';
                }
            } else if (day === 6) { // Saturday
                if (time >= 10 && time < 16) {
                    isOpen = true;
                    statusText = 'Jetzt geoffnet';
                } else if (time < 10) {
                    statusText = 'Heute geoffnet ab 10:00 Uhr';
                } else {
                    statusText = 'Montag wieder geoffnet ab 10:00 Uhr';
                }
            } else { // Sunday
                statusText = 'Montag wieder geoffnet ab 10:00 Uhr';
            }

            const statusBadge = document.getElementById('statusBadge');
            const currentStatus = document.getElementById('currentStatus');

            if (isOpen) {
                statusBadge.innerHTML = '<span class="w-3 h-3 bg-green-500 rounded-full pulse-green"></span><span class="text-sm">' + statusText + '</span>';
                currentStatus.className = 'mt-6 p-4 rounded-xl bg-green-500/20 border border-green-500/30';
                currentStatus.innerHTML = '<div class="flex items-center gap-3"><span class="w-3 h-3 bg-green-500 rounded-full pulse-green"></span><span class="font-semibold text-green-400">' + statusText + '</span></div>';
            } else {
                statusBadge.innerHTML = '<span class="w-3 h-3 bg-red-500 rounded-full"></span><span class="text-sm">Geschlossen</span>';
                currentStatus.className = 'mt-6 p-4 rounded-xl bg-red-500/20 border border-red-500/30';
                currentStatus.innerHTML = '<div class="flex items-center gap-3"><span class="w-3 h-3 bg-red-500 rounded-full"></span><div><span class="font-semibold text-red-400">Geschlossen</span><p class="text-sm text-gray-400 mt-1">' + statusText + '</p></div></div>';
            }
        }

        updateOpenStatus();
        setInterval(updateOpenStatus, 60000);

        // FAQ Toggle
        function toggleFaq(button) {
            const content = button.nextElementSibling;
            const icon = button.querySelector('i');

            content.classList.toggle('hidden');
            icon.classList.toggle('rotate-45');
        }

        // Cookie Banner
        function showCookieBanner() {
            if (!localStorage.getItem('cookieConsent')) {
                document.getElementById('cookieBanner').classList.remove('hidden');
            }
        }

        function acceptCookies() {
            localStorage.setItem('cookieConsent', 'accepted');
            document.getElementById('cookieBanner').classList.add('hidden');
        }

        function declineCookies() {
            localStorage.setItem('cookieConsent', 'declined');
            document.getElementById('cookieBanner').classList.add('hidden');
        }

        // Show cookie banner after 2 seconds
        setTimeout(showCookieBanner, 2000);

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    </script>
</body>
</html>
