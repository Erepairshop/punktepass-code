<?php
/**
 * PunktePass - Partner Pitch Page
 * Professional presentation page for potential wholesale partners
 * Route: /formular/partner
 * Supports: DE / EN language switcher
 *
 * Author: Erik Borota / PunktePass
 */

if (!defined('ABSPATH')) exit;

class PPV_Repair_Partner {

    private static function get_translations() {
        return [
            'de' => [
                'html_lang'        => 'de',
                'page_title'       => 'Reparaturpass Partner-Programm | PunktePass',
                'badge'            => 'Partner-Programm',
                'hero_h1'          => 'Mehrwert f&uuml;r Ihre Kunden.<br><span>Kostenlos.</span>',
                'hero_sub'         => 'Bieten Sie Ihren Werkstatt-Kunden eine professionelle digitale Reparaturverwaltung &ndash; v&ouml;llig kostenlos. Kein Risiko, kein Aufwand, maximaler Mehrwert.',

                // Problem
                'problem_title'    => 'Das Problem Ihrer Kunden',
                'problem_sub'      => 'Die meisten Reparaturwerkst&auml;tten arbeiten noch analog &ndash; das kostet Zeit, Geld und Kunden.',
                'prob1_title'      => 'Papier-Chaos',
                'prob1_text'       => 'Handschriftliche Reparaturzettel gehen verloren, sind unleserlich und nicht durchsuchbar.',
                'prob2_title'      => 'Zeitverlust',
                'prob2_text'       => 'Jeder Auftrag wird manuell erfasst. Das kostet 5-10 Minuten pro Kunde &ndash; jeden Tag.',
                'prob3_title'      => 'Kundenanfragen',
                'prob3_text'       => '&ldquo;Wie ist der Status meiner Reparatur?&rdquo; &ndash; st&auml;ndige Anrufe, die den Betrieb st&ouml;ren.',
                'prob4_title'      => 'Teure Software',
                'prob4_text'       => 'Professionelle L&ouml;sungen kosten oft &euro;50-100+/Monat &ndash; f&uuml;r kleine Werkst&auml;tten zu teuer.',

                // Solution
                'solution_title'   => 'Unsere L&ouml;sung: Reparaturpass',
                'solution_sub'     => 'Eine vollst&auml;ndige digitale Reparaturverwaltung &ndash; kostenlos nutzbar, sofort einsatzbereit.',
                'sol_hero_title'   => 'Digitaler Reparaturbon',
                'sol_hero_text'    => 'Kunden f&uuml;llen das Formular online aus. Die Werkstatt erh&auml;lt alle Daten digital &ndash; kein Papier, keine Fehler.',
                'feat1_title'      => 'Online-Formular',
                'feat1_text'       => 'Eigene URL pro Werkstatt mit individuellem Branding',
                'feat2_title'      => 'Rechnungen & Bons',
                'feat2_text'       => 'Automatische PDF-Erstellung mit Firmenlogo',
                'feat3_title'      => 'Statistiken',
                'feat3_text'       => 'Dashboard mit CSV/PDF-Export aller Reparaturen',
                'feat4_title'      => 'Status-Tracking',
                'feat4_text'       => 'Kunden verfolgen ihren Auftrag per QR-Code',
                'feat5_title'      => 'Kundenverwaltung',
                'feat5_text'       => 'Automatische Kundenhistorie und CRM-Funktionen',
                'feat6_title'      => 'Multi-Filiale',
                'feat6_text'       => 'Mehrere Standorte unter einem Account verwalten',
                'feat7_title'      => 'Treuepunkte-System',
                'feat7_text'       => 'Integriertes Punktesystem zur Kundenbindung &ndash; automatisch bei jeder Reparatur',

                // Stats
                'stat1_val'        => '50',
                'stat1_label'      => 'Formulare / Monat gratis',
                'stat2_val'        => '0&euro;',
                'stat2_label'      => 'Einrichtungsgeb&uuml;hr',
                'stat3_val'        => '2 min',
                'stat3_label'      => 'Registrierung',
                'stat4_val'        => '&infin;',
                'stat4_label'      => 'Kein Vertrag, kein Limit',

                // Win-Win
                'winwin_title'     => 'Win-Win Partnerschaft',
                'winwin_sub'       => 'Eine Partnerschaft, die beiden Seiten Mehrwert bringt &ndash; ohne Kosten.',
                'win_partner_tag'  => 'F&uuml;r Sie als Gro&szlig;h&auml;ndler',
                'win_partner_h3'   => 'Kundenbindung st&auml;rken',
                'win_p1'           => 'Ihre Kunden werden professioneller &ndash; und bestellen mehr bei Ihnen',
                'win_p2'           => 'Sie positionieren sich als &ldquo;More Than Spareparts&rdquo;-Partner',
                'win_p3'           => 'Kostenloser Mehrwert f&uuml;r Ihr Portfolio &ndash; kein Investment n&ouml;tig',
                'win_p4'           => 'Co-Branded Pr&auml;senz: &ldquo;Empfohlen von [Ihr Name]&rdquo;',
                'win_p5'           => 'Optionale Verg&uuml;tung bei Premium-Upgrades Ihrer Kunden',
                'win_customer_tag' => 'F&uuml;r Ihre Werkstatt-Kunden',
                'win_customer_h3'  => 'Sofort professioneller',
                'win_c1'           => 'Kostenlose digitale Reparaturverwaltung &ndash; sofort nutzbar',
                'win_c2'           => 'Kein Papier-Chaos mehr &ndash; alles digital und durchsuchbar',
                'win_c3'           => 'Professioneller Auftritt gegen&uuml;ber Endkunden',
                'win_c4'           => 'Status-Tracking reduziert &ldquo;Wo ist mein Ger&auml;t?&rdquo;-Anrufe um 80%',
                'win_c5'           => 'Spart 30+ Minuten t&auml;glich durch automatisierte Prozesse',

                // Visibility Boost
                'visibility_title' => 'Ihre Sichtbarkeit in jeder Werkstatt',
                'visibility_sub'   => 'Als Partner werden Sie direkt im Dashboard aller Werkst&auml;tten als empfohlener Lieferant angezeigt &ndash; mit Logo, Name und Link zu Ihrem Webshop.',
                'vis1_title'       => 'Sichtbar im Dashboard',
                'vis1_text'        => 'Jede Werkstatt sieht Ihren Webshop-Link im t&auml;glichen Arbeitsbereich &ndash; dort, wo sie ihre Reparaturen verwalten.',
                'vis2_title'       => 'Direkte Bestellungen',
                'vis2_text'        => 'Werkst&auml;tten k&ouml;nnen mit einem Klick Ihren Webshop &ouml;ffnen &ndash; wenn sie Ersatzteile oder Zubeh&ouml;r brauchen, sind Sie sofort da.',
                'vis3_title'       => 'Wachsende Reichweite',
                'vis3_text'        => 'Je mehr Werkst&auml;tten den Reparaturpass nutzen, desto mehr potenzielle K&auml;ufer sehen Ihr Angebot &ndash; automatisch.',

                // Partnership Models
                'models_title'     => 'Partnerschafts-Modelle',
                'models_sub'       => 'Flexibel und unverbindlich &ndash; w&auml;hlen Sie, was am besten passt.',
                'model1_title'     => 'Newsletter & Webshop',
                'model1_text'      => 'Erw&auml;hnung in Ihrem Newsletter oder Banner in Ihrem Webshop. Minimaler Aufwand, sofortige Reichweite.',
                'model1_d1'        => '30 Min. Aufwand einmalig',
                'model1_d2'        => 'Kostenfrei',
                'model2_badge'     => 'Empfohlen',
                'model2_title'     => 'Paketbeileger',
                'model2_text'      => 'Ein kleiner Flyer in jeder Lieferung. Ihre Kunden entdecken den Reparaturpass beim Auspacken.',
                'model2_d1'        => 'H&ouml;chste Sichtbarkeit',
                'model2_d2'        => 'Wir liefern die Flyer kostenlos',
                'model3_title'     => 'Co-Branded',
                'model3_text'      => 'Eigene Partner-Landingpage mit Ihrem Logo. &ldquo;Empfohlen von [Ihr Unternehmen]&rdquo; im Reparaturpass.',
                'model3_d1'        => 'Exklusiv f&uuml;r Hauptpartner',
                'model3_d2'        => 'Revenue-Share m&ouml;glich',
                'model4_badge'     => 'Neu',
                'model4_title'     => 'Embed Widget',
                'model4_text'      => 'Fertiges JavaScript-Widget f&uuml;r Ihren Webshop. Ein Klick-Button, der Ihren Kunden den Reparaturpass direkt pr&auml;sentiert &ndash; in Ihrem Design.',
                'model4_d1'        => '1 Zeile Code &ndash; sofort live',
                'model4_d2'        => 'Eigenes Dashboard mit Statistiken',

                // How it works
                'steps_title'      => 'So einfach geht&rsquo;s',
                'steps_sub'        => 'In 4 Schritten zur Partnerschaft &ndash; ohne Vertr&auml;ge, ohne Verpflichtungen.',
                'step1_title'      => 'Testen',
                'step1_text'       => 'Registrieren Sie sich selbst und erleben Sie den Reparaturpass aus Werkstatt-Sicht.',
                'step2_title'      => 'Vereinbaren',
                'step2_text'       => 'Wir besprechen, welches Modell am besten zu Ihnen passt.',
                'step3_title'      => 'Starten',
                'step3_text'       => 'Wir liefern Banner, Flyer oder Co-Branding &ndash; Sie teilen es mit Ihren Kunden.',
                'step4_title'      => 'Profitieren',
                'step4_text'       => 'Zufriedenere Kunden, st&auml;rkere Bindung &ndash; und optional Revenue-Share.',

                // Pricing
                'pricing_title'    => 'Preismodell f&uuml;r Werkst&auml;tten',
                'pricing_sub'      => 'Ihre Kunden starten kostenlos. Premium nur bei Bedarf.',
                'price_free_tag'   => 'Kostenlos',
                'price_free_val'   => '0&euro;',
                'price_per_month'  => '/ Monat',
                'price_free_desc'  => 'F&uuml;r kleine Werkst&auml;tten',
                'price_f1'         => '50 Formulare pro Monat',
                'price_f2'         => 'Eigenes Reparaturformular',
                'price_f3'         => 'Kunden-Tracking per QR',
                'price_f4'         => 'Dashboard & &Uuml;bersicht',
                'price_f5'         => 'Unbegrenzt nutzbar',
                'price_f6'         => 'Treuepunkte-System inklusive',
                'price_prem_tag'   => 'Premium',
                'price_prem_val'   => '39&euro;',
                'price_prem_desc'  => 'F&uuml;r professionelle Betriebe',
                'price_p1'         => 'Unbegrenzte Formulare',
                'price_p2'         => 'Rechnungserstellung',
                'price_p3'         => 'CSV/PDF Export',
                'price_p4'         => 'Multi-Filiale Support',
                'price_p5'         => 'Ankauf-Modul',
                'price_p6'         => 'Priorit&auml;ts-Support',

                // CTA
                'cta_title'        => 'Bereit f&uuml;r eine Partnerschaft?',
                'cta_text'         => 'Lassen Sie uns gemeinsam die Reparaturbranche digitalisieren. Kein Risiko, kein Aufwand &ndash; nur Mehrwert.',
                'cta_demo'         => 'Live-Demo ansehen',
                'cta_contact'      => 'Kontakt aufnehmen',
                'cta_mail_subject' => 'Partnerschaft%20Reparaturpass',

                // Footer
                'footer_conf'      => 'Dieses Dokument ist vertraulich.',
                'print_title'      => 'Als PDF drucken',
            ],
            'en' => [
                'html_lang'        => 'en',
                'page_title'       => 'Repair Pass Partner Program | PunktePass',
                'badge'            => 'Partner Program',
                'hero_h1'          => 'Added value for your customers.<br><span>Free of charge.</span>',
                'hero_sub'         => 'Offer your repair shop customers a professional digital repair management system &ndash; completely free. No risk, no effort, maximum value.',

                // Problem
                'problem_title'    => 'Your Customers\' Problem',
                'problem_sub'      => 'Most repair shops still work with pen and paper &ndash; costing time, money, and customers.',
                'prob1_title'      => 'Paper Chaos',
                'prob1_text'       => 'Handwritten repair slips get lost, are illegible, and impossible to search through.',
                'prob2_title'      => 'Time Wasted',
                'prob2_text'       => 'Every order is recorded manually. That costs 5-10 minutes per customer &ndash; every day.',
                'prob3_title'      => 'Customer Inquiries',
                'prob3_text'       => '&ldquo;What\'s the status of my repair?&rdquo; &ndash; constant calls that disrupt daily business.',
                'prob4_title'      => 'Expensive Software',
                'prob4_text'       => 'Professional solutions often cost &euro;50-100+/month &ndash; too expensive for small workshops.',

                // Solution
                'solution_title'   => 'Our Solution: Repair Pass',
                'solution_sub'     => 'A complete digital repair management system &ndash; free to use, ready to go.',
                'sol_hero_title'   => 'Digital Repair Receipt',
                'sol_hero_text'    => 'Customers fill out the form online. The workshop receives all data digitally &ndash; no paper, no errors.',
                'feat1_title'      => 'Online Form',
                'feat1_text'       => 'Custom URL per workshop with individual branding',
                'feat2_title'      => 'Invoices & Receipts',
                'feat2_text'       => 'Automatic PDF generation with company logo',
                'feat3_title'      => 'Statistics',
                'feat3_text'       => 'Dashboard with CSV/PDF export of all repairs',
                'feat4_title'      => 'Status Tracking',
                'feat4_text'       => 'Customers track their order via QR code',
                'feat5_title'      => 'Customer Management',
                'feat5_text'       => 'Automatic customer history and CRM features',
                'feat6_title'      => 'Multi-Location',
                'feat6_text'       => 'Manage multiple locations under one account',
                'feat7_title'      => 'Loyalty Points System',
                'feat7_text'       => 'Integrated points system for customer retention &ndash; automatic with every repair',

                // Stats
                'stat1_val'        => '50',
                'stat1_label'      => 'Forms / month free',
                'stat2_val'        => '&euro;0',
                'stat2_label'      => 'Setup fee',
                'stat3_val'        => '2 min',
                'stat3_label'      => 'Registration',
                'stat4_val'        => '&infin;',
                'stat4_label'      => 'No contract, no limits',

                // Win-Win
                'winwin_title'     => 'Win-Win Partnership',
                'winwin_sub'       => 'A partnership that delivers value to both sides &ndash; at no cost.',
                'win_partner_tag'  => 'For You as Wholesaler',
                'win_partner_h3'   => 'Strengthen Customer Loyalty',
                'win_p1'           => 'Your customers become more professional &ndash; and order more from you',
                'win_p2'           => 'Position yourself as a &ldquo;More Than Spareparts&rdquo; partner',
                'win_p3'           => 'Free added value for your portfolio &ndash; no investment needed',
                'win_p4'           => 'Co-branded presence: &ldquo;Recommended by [Your Name]&rdquo;',
                'win_p5'           => 'Optional commission on Premium upgrades from your customers',
                'win_customer_tag' => 'For Your Repair Shop Customers',
                'win_customer_h3'  => 'Instantly More Professional',
                'win_c1'           => 'Free digital repair management &ndash; ready to use immediately',
                'win_c2'           => 'No more paper chaos &ndash; everything digital and searchable',
                'win_c3'           => 'Professional appearance towards end customers',
                'win_c4'           => 'Status tracking reduces &ldquo;Where is my device?&rdquo; calls by 80%',
                'win_c5'           => 'Saves 30+ minutes daily through automated processes',

                // Visibility Boost
                'visibility_title' => 'Your Visibility in Every Workshop',
                'visibility_sub'   => 'As a partner, you are featured directly in every workshop&rsquo;s dashboard as a recommended supplier &ndash; with your logo, name, and a link to your webshop.',
                'vis1_title'       => 'Visible in the Dashboard',
                'vis1_text'        => 'Every workshop sees your webshop link in their daily workspace &ndash; right where they manage their repairs.',
                'vis2_title'       => 'Direct Orders',
                'vis2_text'        => 'Workshops can open your webshop with one click &ndash; when they need spare parts or accessories, you&rsquo;re right there.',
                'vis3_title'       => 'Growing Reach',
                'vis3_text'        => 'The more workshops use the Repair Pass, the more potential buyers see your offer &ndash; automatically.',

                // Partnership Models
                'models_title'     => 'Partnership Models',
                'models_sub'       => 'Flexible and non-binding &ndash; choose what fits best.',
                'model1_title'     => 'Newsletter & Webshop',
                'model1_text'      => 'Mention in your newsletter or banner in your webshop. Minimal effort, immediate reach.',
                'model1_d1'        => '30 min. one-time effort',
                'model1_d2'        => 'Free of charge',
                'model2_badge'     => 'Recommended',
                'model2_title'     => 'Package Insert',
                'model2_text'      => 'A small flyer in every delivery. Your customers discover the Repair Pass when unboxing.',
                'model2_d1'        => 'Highest visibility',
                'model2_d2'        => 'We supply the flyers for free',
                'model3_title'     => 'Co-Branded',
                'model3_text'      => 'Custom partner landing page with your logo. &ldquo;Recommended by [Your Company]&rdquo; in the Repair Pass.',
                'model3_d1'        => 'Exclusive for main partners',
                'model3_d2'        => 'Revenue share possible',
                'model4_badge'     => 'New',
                'model4_title'     => 'Embed Widget',
                'model4_text'      => 'Ready-made JavaScript widget for your webshop. A click button that presents the Repair Pass directly to your customers &ndash; in your branding.',
                'model4_d1'        => '1 line of code &ndash; instantly live',
                'model4_d2'        => 'Own dashboard with statistics',

                // How it works
                'steps_title'      => 'How It Works',
                'steps_sub'        => '4 simple steps to partnership &ndash; no contracts, no obligations.',
                'step1_title'      => 'Try It',
                'step1_text'       => 'Register yourself and experience the Repair Pass from a workshop perspective.',
                'step2_title'      => 'Discuss',
                'step2_text'       => 'We\'ll talk about which model fits you best.',
                'step3_title'      => 'Launch',
                'step3_text'       => 'We deliver banners, flyers, or co-branding &ndash; you share it with your customers.',
                'step4_title'      => 'Profit',
                'step4_text'       => 'Happier customers, stronger loyalty &ndash; and optional revenue share.',

                // Pricing
                'pricing_title'    => 'Pricing for Workshops',
                'pricing_sub'      => 'Your customers start for free. Premium only when needed.',
                'price_free_tag'   => 'Free',
                'price_free_val'   => '&euro;0',
                'price_per_month'  => '/ month',
                'price_free_desc'  => 'For small workshops',
                'price_f1'         => '50 forms per month',
                'price_f2'         => 'Custom repair form',
                'price_f3'         => 'Customer tracking via QR',
                'price_f4'         => 'Dashboard & overview',
                'price_f5'         => 'Unlimited usage',
                'price_f6'         => 'Loyalty points system included',
                'price_prem_tag'   => 'Premium',
                'price_prem_val'   => '&euro;39',
                'price_prem_desc'  => 'For professional businesses',
                'price_p1'         => 'Unlimited forms',
                'price_p2'         => 'Invoice generation',
                'price_p3'         => 'CSV/PDF export',
                'price_p4'         => 'Multi-location support',
                'price_p5'         => 'Buy-back module',
                'price_p6'         => 'Priority support',

                // CTA
                'cta_title'        => 'Ready for a Partnership?',
                'cta_text'         => 'Let\'s digitalize the repair industry together. No risk, no effort &ndash; just value.',
                'cta_demo'         => 'View Live Demo',
                'cta_contact'      => 'Get in Touch',
                'cta_mail_subject' => 'Partnership%20Repair%20Pass',

                // Footer
                'footer_conf'      => 'This document is confidential.',
                'print_title'      => 'Print as PDF',
            ],
            'hu' => [
                'html_lang'        => 'hu',
                'page_title'       => 'Reparaturpass Partner-Program | PunktePass',
                'badge'            => 'Partner-Program',
                'hero_h1'          => 'Többletérték az ügyfelei számára.<br><span>Ingyenesen.</span>',
                'hero_sub'         => 'Kínáljon ügyfeleinek professzionális digitális javításkezelést &ndash; teljesen ingyen. Nincs kockázat, nincs ráfordítás, maximális többletérték.',

                'problem_title'    => 'Az ügyfelei problémája',
                'problem_sub'      => 'A legtöbb javítóműhely még mindig papír alapon dolgozik &ndash; ez időt, pénzt és ügyfeleket jelent.',
                'prob1_title'      => 'Papír káosz',
                'prob1_text'       => 'A kézzel írt javítási cédulák elvesznek, olvashatatlanok és nem kereshetők.',
                'prob2_title'      => 'Időveszteség',
                'prob2_text'       => 'Minden megrendelést kézzel rögzítenek. Ez napi 5-10 percet jelent ügyelenként.',
                'prob3_title'      => 'Ügyfél-megkeresések',
                'prob3_text'       => '&bdquo;Mi a javításom állapota?&rdquo; &ndash; állandó hívások, amelyek zavarják a munkát.',
                'prob4_title'      => 'Drága szoftver',
                'prob4_text'       => 'A professzionális megoldások gyakran &euro;50-100+/hó &ndash; kis műhelyek számára túl drága.',

                'solution_title'   => 'A mi megoldásunk: Reparaturpass',
                'solution_sub'     => 'Teljes körű digitális javításkezelés &ndash; ingyenesen használható, azonnal bevethető.',
                'sol_hero_title'   => 'Digitális javítási bizonylat',
                'sol_hero_text'    => 'Az ügyfelek online töltik ki az űrlapot. A műhely minden adatot digitálisan kap &ndash; nincs papír, nincs hiba.',
                'feat1_title'      => 'Online űrlap',
                'feat1_text'       => 'Saját URL minden műhelyhez egyedi arculattal',
                'feat2_title'      => 'Számlák és bizonylatok',
                'feat2_text'       => 'Automatikus PDF-készítés céges logóval',
                'feat3_title'      => 'Statisztikák',
                'feat3_text'       => 'Dashboard CSV/PDF exporttal minden javításról',
                'feat4_title'      => 'Állapotkövetés',
                'feat4_text'       => 'Az ügyfelek QR-kódon keresztül követhetik megrendelésüket',
                'feat5_title'      => 'Ügyfélkezelés',
                'feat5_text'       => 'Automatikus ügyféltörténet és CRM funkciók',
                'feat6_title'      => 'Több telephely',
                'feat6_text'       => 'Több helyszín kezelése egy fiók alatt',
                'feat7_title'      => 'Hűségpont rendszer',
                'feat7_text'       => 'Integrált pontrendszer az ügyfélmegtartáshoz &ndash; automatikus minden javításnál',

                'stat1_val'        => '50',
                'stat1_label'      => 'Űrlap / hó ingyen',
                'stat2_val'        => '0&euro;',
                'stat2_label'      => 'Beállítási díj',
                'stat3_val'        => '2 perc',
                'stat3_label'      => 'Regisztráció',
                'stat4_val'        => '&infin;',
                'stat4_label'      => 'Nincs szerződés, nincs limit',

                'winwin_title'     => 'Win-Win partnerség',
                'winwin_sub'       => 'Olyan partnerség, amely mindkét fél számára értéket teremt &ndash; költségek nélkül.',
                'win_partner_tag'  => 'Az Ön számára mint nagykereskedő',
                'win_partner_h3'   => 'Ügyfélhűség erősítése',
                'win_p1'           => 'Ügyfelei professzionálisabbak lesznek &ndash; és többet rendelnek Öntől',
                'win_p2'           => 'Pozícionálja magát &bdquo;Több mint alkatrész&rdquo; partnerként',
                'win_p3'           => 'Ingyenes többletérték a portfóliójához &ndash; befektetés nem szükséges',
                'win_p4'           => 'Co-branded jelenlét: &bdquo;Ajánlja: [Az Ön neve]&rdquo;',
                'win_p5'           => 'Opcionális jutalék az ügyfelei Premium frissítéseinél',
                'win_customer_tag' => 'A műhely-ügyfelei számára',
                'win_customer_h3'  => 'Azonnal professzionálisabb',
                'win_c1'           => 'Ingyenes digitális javításkezelés &ndash; azonnal használható',
                'win_c2'           => 'Nincs több papír káosz &ndash; minden digitális és kereshető',
                'win_c3'           => 'Professzionális megjelenés a végfelhasználók felé',
                'win_c4'           => 'Az állapotkövetés 80%-kal csökkenti a &bdquo;Hol van a készülékem?&rdquo; hívásokat',
                'win_c5'           => 'Naponta 30+ percet takarít meg automatizált folyamatokkal',

                'visibility_title' => 'Az Ön láthatósága minden műhelyben',
                'visibility_sub'   => 'Partnerként közvetlenül megjelenik minden műhely dashboardjában ajánlott beszállítóként &ndash; logóval, névvel és webshop-linkkel.',
                'vis1_title'       => 'Látható a dashboardban',
                'vis1_text'        => 'Minden műhely látja a webshop-linkjét a napi munkaterületén &ndash; ott, ahol a javításokat kezelik.',
                'vis2_title'       => 'Közvetlen rendelések',
                'vis2_text'        => 'A műhelyek egy kattintással megnyithatják webshopját &ndash; ha alkatrészekre vagy kiegészítőkre van szükségük, Ön azonnal ott van.',
                'vis3_title'       => 'Növekvő elérés',
                'vis3_text'        => 'Minél több műhely használja a Reparaturpass-t, annál több potenciális vevő látja ajánlatát &ndash; automatikusan.',

                'models_title'     => 'Partneri modellek',
                'models_sub'       => 'Rugalmas és kötelezettségmentes &ndash; válassza ki, ami a legjobban illik.',
                'model1_title'     => 'Hírlevél és Webshop',
                'model1_text'      => 'Említés a hírlevelében vagy banner a webshopjában. Minimális ráfordítás, azonnali elérés.',
                'model1_d1'        => '30 perc egyszeri ráfordítás',
                'model1_d2'        => 'Ingyenes',
                'model2_badge'     => 'Ajánlott',
                'model2_title'     => 'Csomagmelléklet',
                'model2_text'      => 'Kis szórólap minden szállítmányban. Ügyfelei a kicsomagolásnál fedezik fel a Reparaturpass-t.',
                'model2_d1'        => 'Legnagyobb láthatóság',
                'model2_d2'        => 'A szórólapokat ingyenesen szállítjuk',
                'model3_title'     => 'Co-Branded',
                'model3_text'      => 'Egyedi partner landing oldal az Ön logójával. &bdquo;Ajánlja: [Az Ön cége]&rdquo; a Reparaturpass-ban.',
                'model3_d1'        => 'Kizárólag fő partnereknek',
                'model3_d2'        => 'Revenue-share lehetséges',
                'model4_badge'     => 'Új',
                'model4_title'     => 'Embed Widget',
                'model4_text'      => 'Kész JavaScript widget a webshopjához. Egy kattintás gomb, amely közvetlenül bemutatja a Reparaturpass-t ügyfeleinek &ndash; az Ön dizájnjában.',
                'model4_d1'        => '1 sor kód &ndash; azonnal éles',
                'model4_d2'        => 'Saját dashboard statisztikákkal',

                'steps_title'      => 'Ilyen egyszerű',
                'steps_sub'        => '4 lépésben a partnerségig &ndash; szerződések és kötelezettségek nélkül.',
                'step1_title'      => 'Tesztelés',
                'step1_text'       => 'Regisztráljon és tapasztalja meg a Reparaturpass-t műhely szemszögéből.',
                'step2_title'      => 'Megbeszélés',
                'step2_text'       => 'Megbeszéljük, melyik modell illik a legjobban Önhöz.',
                'step3_title'      => 'Indítás',
                'step3_text'       => 'Szállítjuk a bannert, szórólapot vagy co-brandinget &ndash; Ön megosztja ügyfeleivel.',
                'step4_title'      => 'Profitálás',
                'step4_text'       => 'Elégedettebb ügyfelek, erősebb kötődés &ndash; és opcionális revenue-share.',

                'pricing_title'    => 'Árazás műhelyek számára',
                'pricing_sub'      => 'Ügyfelei ingyenesen indulnak. Premium csak szükség esetén.',
                'price_free_tag'   => 'Ingyenes',
                'price_free_val'   => '0&euro;',
                'price_per_month'  => '/ hó',
                'price_free_desc'  => 'Kis műhelyek számára',
                'price_f1'         => '50 űrlap havonta',
                'price_f2'         => 'Saját javítási űrlap',
                'price_f3'         => 'Ügyfélkövetés QR-kóddal',
                'price_f4'         => 'Dashboard és áttekintés',
                'price_f5'         => 'Korlátlanul használható',
                'price_f6'         => 'Hűségpont rendszer benne',
                'price_prem_tag'   => 'Premium',
                'price_prem_val'   => '39&euro;',
                'price_prem_desc'  => 'Professzionális üzemek számára',
                'price_p1'         => 'Korlátlan űrlapok',
                'price_p2'         => 'Számla készítés',
                'price_p3'         => 'CSV/PDF export',
                'price_p4'         => 'Több telephely támogatás',
                'price_p5'         => 'Felvásárlás modul',
                'price_p6'         => 'Kiemelt támogatás',

                'cta_title'        => 'Készen áll a partnerségre?',
                'cta_text'         => 'Digitalizáljuk együtt a javítási iparágat. Nincs kockázat, nincs ráfordítás &ndash; csak érték.',
                'cta_demo'         => 'Élő demó megtekintése',
                'cta_contact'      => 'Kapcsolatfelvétel',
                'cta_mail_subject' => 'Partners%C3%A9g%20Reparaturpass',

                'footer_conf'      => 'Ez a dokumentum bizalmas.',
                'print_title'      => 'Nyomtatás PDF-ként',
            ],
            'ro' => [
                'html_lang'        => 'ro',
                'page_title'       => 'Programul de Parteneriat Reparaturpass | PunktePass',
                'badge'            => 'Program de Parteneriat',
                'hero_h1'          => 'Valoare adăugată pentru clienții dvs.<br><span>Gratuit.</span>',
                'hero_sub'         => 'Oferiți clienților dvs. din ateliere un sistem profesional de gestionare digitală a reparațiilor &ndash; complet gratuit. Fără riscuri, fără efort, valoare maximă.',

                'problem_title'    => 'Problema clienților dvs.',
                'problem_sub'      => 'Majoritatea atelierelor lucrează încă pe hârtie &ndash; asta costă timp, bani și clienți.',
                'prob1_title'      => 'Haos pe hârtie',
                'prob1_text'       => 'Bonurile scrise de mână se pierd, sunt ilizibile și imposibil de căutat.',
                'prob2_title'      => 'Timp pierdut',
                'prob2_text'       => 'Fiecare comandă este înregistrată manual. Asta costă 5-10 minute per client &ndash; zilnic.',
                'prob3_title'      => 'Întrebări clienți',
                'prob3_text'       => '&bdquo;Care este starea reparației mele?&rdquo; &ndash; apeluri constante care perturbă activitatea.',
                'prob4_title'      => 'Software scump',
                'prob4_text'       => 'Soluțiile profesionale costă adesea &euro;50-100+/lună &ndash; prea scump pentru atelierele mici.',

                'solution_title'   => 'Soluția noastră: Reparaturpass',
                'solution_sub'     => 'Un sistem complet de gestionare digitală a reparațiilor &ndash; gratuit, gata de utilizare.',
                'sol_hero_title'   => 'Bon digital de reparație',
                'sol_hero_text'    => 'Clienții completează formularul online. Atelierul primește toate datele digital &ndash; fără hârtie, fără erori.',
                'feat1_title'      => 'Formular online',
                'feat1_text'       => 'URL propriu per atelier cu branding individual',
                'feat2_title'      => 'Facturi și bonuri',
                'feat2_text'       => 'Generare automată PDF cu logo-ul companiei',
                'feat3_title'      => 'Statistici',
                'feat3_text'       => 'Dashboard cu export CSV/PDF al tuturor reparațiilor',
                'feat4_title'      => 'Urmărire status',
                'feat4_text'       => 'Clienții urmăresc comanda prin cod QR',
                'feat5_title'      => 'Gestionare clienți',
                'feat5_text'       => 'Istoric automat al clienților și funcții CRM',
                'feat6_title'      => 'Multi-locație',
                'feat6_text'       => 'Gestionați mai multe locații sub un singur cont',
                'feat7_title'      => 'Sistem de puncte de fidelitate',
                'feat7_text'       => 'Sistem integrat de puncte pentru fidelizarea clienților &ndash; automat la fiecare reparație',

                'stat1_val'        => '50',
                'stat1_label'      => 'Formulare / lună gratuit',
                'stat2_val'        => '0&euro;',
                'stat2_label'      => 'Taxă de configurare',
                'stat3_val'        => '2 min',
                'stat3_label'      => 'Înregistrare',
                'stat4_val'        => '&infin;',
                'stat4_label'      => 'Fără contract, fără limite',

                'winwin_title'     => 'Parteneriat Win-Win',
                'winwin_sub'       => 'Un parteneriat care aduce valoare ambelor părți &ndash; fără costuri.',
                'win_partner_tag'  => 'Pentru dvs. ca angrosist',
                'win_partner_h3'   => 'Întăriți fidelitatea clienților',
                'win_p1'           => 'Clienții dvs. devin mai profesioniști &ndash; și comandă mai mult de la dvs.',
                'win_p2'           => 'Poziționați-vă ca partener &bdquo;Mai mult decât piese de schimb&rdquo;',
                'win_p3'           => 'Valoare adăugată gratuită pentru portofoliul dvs. &ndash; fără investiție necesară',
                'win_p4'           => 'Prezență co-branded: &bdquo;Recomandat de [Numele dvs.]&rdquo;',
                'win_p5'           => 'Comision opțional la upgrade-urile Premium ale clienților dvs.',
                'win_customer_tag' => 'Pentru clienții dvs. din ateliere',
                'win_customer_h3'  => 'Instant mai profesioniști',
                'win_c1'           => 'Gestionare digitală gratuită a reparațiilor &ndash; gata de utilizare imediat',
                'win_c2'           => 'Fără haos pe hârtie &ndash; totul digital și căutabil',
                'win_c3'           => 'Aspect profesional față de clienții finali',
                'win_c4'           => 'Urmărirea statusului reduce apelurile &bdquo;Unde este dispozitivul meu?&rdquo; cu 80%',
                'win_c5'           => 'Economisește 30+ minute zilnic prin procese automatizate',

                'visibility_title' => 'Vizibilitatea dvs. în fiecare atelier',
                'visibility_sub'   => 'Ca partener, sunteți afișat direct în dashboard-ul fiecărui atelier ca furnizor recomandat &ndash; cu logo, nume și link către webshop-ul dvs.',
                'vis1_title'       => 'Vizibil în dashboard',
                'vis1_text'        => 'Fiecare atelier vede link-ul webshop-ului dvs. în spațiul de lucru zilnic &ndash; acolo unde își gestionează reparațiile.',
                'vis2_title'       => 'Comenzi directe',
                'vis2_text'        => 'Atelierele pot deschide webshop-ul dvs. cu un clic &ndash; când au nevoie de piese de schimb, sunteți acolo.',
                'vis3_title'       => 'Acoperire în creștere',
                'vis3_text'        => 'Cu cât mai multe ateliere folosesc Reparaturpass, cu atât mai mulți cumpărători potențiali văd oferta dvs. &ndash; automat.',

                'models_title'     => 'Modele de parteneriat',
                'models_sub'       => 'Flexibil și fără obligații &ndash; alegeți ce se potrivește cel mai bine.',
                'model1_title'     => 'Newsletter și Webshop',
                'model1_text'      => 'Mențiune în newsletter-ul dvs. sau banner în webshop. Efort minim, acoperire imediată.',
                'model1_d1'        => '30 min. efort o singură dată',
                'model1_d2'        => 'Gratuit',
                'model2_badge'     => 'Recomandat',
                'model2_title'     => 'Insert în pachet',
                'model2_text'      => 'Un mic flyer în fiecare livrare. Clienții dvs. descoperă Reparaturpass la despachetare.',
                'model2_d1'        => 'Cea mai mare vizibilitate',
                'model2_d2'        => 'Furnizăm flyerele gratuit',
                'model3_title'     => 'Co-Branded',
                'model3_text'      => 'Pagină partener personalizată cu logo-ul dvs. &bdquo;Recomandat de [Compania dvs.]&rdquo; în Reparaturpass.',
                'model3_d1'        => 'Exclusiv pentru parteneri principali',
                'model3_d2'        => 'Revenue-share posibil',
                'model4_badge'     => 'Nou',
                'model4_title'     => 'Widget Embed',
                'model4_text'      => 'Widget JavaScript gata pentru webshop-ul dvs. Un buton care prezintă Reparaturpass direct clienților dvs. &ndash; în designul dvs.',
                'model4_d1'        => '1 linie de cod &ndash; instant live',
                'model4_d2'        => 'Dashboard propriu cu statistici',

                'steps_title'      => 'Cum funcționează',
                'steps_sub'        => '4 pași simpli către parteneriat &ndash; fără contracte, fără obligații.',
                'step1_title'      => 'Testați',
                'step1_text'       => 'Înregistrați-vă și experimentați Reparaturpass din perspectiva atelierului.',
                'step2_title'      => 'Discutați',
                'step2_text'       => 'Discutăm ce model vi se potrivește cel mai bine.',
                'step3_title'      => 'Lansați',
                'step3_text'       => 'Furnizăm bannere, flyere sau co-branding &ndash; dvs. le distribuiți clienților.',
                'step4_title'      => 'Profitați',
                'step4_text'       => 'Clienți mai fericiți, loialitate mai puternică &ndash; și revenue-share opțional.',

                'pricing_title'    => 'Prețuri pentru ateliere',
                'pricing_sub'      => 'Clienții dvs. încep gratuit. Premium doar la nevoie.',
                'price_free_tag'   => 'Gratuit',
                'price_free_val'   => '0&euro;',
                'price_per_month'  => '/ lună',
                'price_free_desc'  => 'Pentru ateliere mici',
                'price_f1'         => '50 formulare pe lună',
                'price_f2'         => 'Formular propriu de reparație',
                'price_f3'         => 'Urmărire clienți prin QR',
                'price_f4'         => 'Dashboard și prezentare generală',
                'price_f5'         => 'Utilizare nelimitată',
                'price_f6'         => 'Sistem de puncte de fidelitate inclus',
                'price_prem_tag'   => 'Premium',
                'price_prem_val'   => '39&euro;',
                'price_prem_desc'  => 'Pentru afaceri profesionale',
                'price_p1'         => 'Formulare nelimitate',
                'price_p2'         => 'Generare facturi',
                'price_p3'         => 'Export CSV/PDF',
                'price_p4'         => 'Suport multi-locație',
                'price_p5'         => 'Modul de achiziție',
                'price_p6'         => 'Suport prioritar',

                'cta_title'        => 'Pregătiți pentru un parteneriat?',
                'cta_text'         => 'Haideți să digitalizăm industria reparațiilor împreună. Fără riscuri, fără efort &ndash; doar valoare.',
                'cta_demo'         => 'Vedeți demo live',
                'cta_contact'      => 'Contactați-ne',
                'cta_mail_subject' => 'Parteneriat%20Reparaturpass',

                'footer_conf'      => 'Acest document este confidențial.',
                'print_title'      => 'Imprimă ca PDF',
            ],
            'it' => [
                'html_lang'        => 'it',
                'page_title'       => 'Programma Partner Reparaturpass | PunktePass',
                'badge'            => 'Programma Partner',
                'hero_h1'          => 'Valore aggiunto per i tuoi clienti.<br><span>Gratuito.</span>',
                'hero_sub'         => 'Offri ai clienti del tuo laboratorio un sistema professionale di gestione digitale delle riparazioni &ndash; completamente gratuito. Nessun rischio, nessuno sforzo, massimo valore.',

                'problem_title'    => 'Il problema dei tuoi clienti',
                'problem_sub'      => 'La maggior parte dei laboratori lavora ancora con carta e penna &ndash; costando tempo, denaro e clienti.',
                'prob1_title'      => 'Caos cartaceo',
                'prob1_text'       => 'Le ricevute scritte a mano si perdono, sono illeggibili e impossibili da cercare.',
                'prob2_title'      => 'Tempo perso',
                'prob2_text'       => 'Ogni ordine viene registrato manualmente. Costa 5-10 minuti per cliente &ndash; ogni giorno.',
                'prob3_title'      => 'Richieste clienti',
                'prob3_text'       => '&ldquo;Qual è lo stato della mia riparazione?&rdquo; &ndash; chiamate costanti che disturbano l&rsquo;attivit&agrave;.',
                'prob4_title'      => 'Software costoso',
                'prob4_text'       => 'Le soluzioni professionali costano spesso &euro;50-100+/mese &ndash; troppo caro per piccoli laboratori.',

                'solution_title'   => 'La nostra soluzione: Reparaturpass',
                'solution_sub'     => 'Un sistema completo di gestione digitale delle riparazioni &ndash; gratuito, pronto all&rsquo;uso.',
                'sol_hero_title'   => 'Ricevuta digitale di riparazione',
                'sol_hero_text'    => 'I clienti compilano il modulo online. Il laboratorio riceve tutti i dati in digitale &ndash; niente carta, niente errori.',
                'feat1_title'      => 'Modulo online',
                'feat1_text'       => 'URL personalizzato per laboratorio con branding individuale',
                'feat2_title'      => 'Fatture e ricevute',
                'feat2_text'       => 'Generazione automatica PDF con logo aziendale',
                'feat3_title'      => 'Statistiche',
                'feat3_text'       => 'Dashboard con esportazione CSV/PDF di tutte le riparazioni',
                'feat4_title'      => 'Tracciamento stato',
                'feat4_text'       => 'I clienti tracciano il loro ordine tramite codice QR',
                'feat5_title'      => 'Gestione clienti',
                'feat5_text'       => 'Storico clienti automatico e funzioni CRM',
                'feat6_title'      => 'Multi-sede',
                'feat6_text'       => 'Gestisci più sedi sotto un unico account',
                'feat7_title'      => 'Sistema punti fedeltà',
                'feat7_text'       => 'Sistema di punti integrato per la fidelizzazione &ndash; automatico ad ogni riparazione',

                'stat1_val'        => '50',
                'stat1_label'      => 'Moduli / mese gratis',
                'stat2_val'        => '0&euro;',
                'stat2_label'      => 'Costo di configurazione',
                'stat3_val'        => '2 min',
                'stat3_label'      => 'Registrazione',
                'stat4_val'        => '&infin;',
                'stat4_label'      => 'Nessun contratto, nessun limite',

                'winwin_title'     => 'Partnership Win-Win',
                'winwin_sub'       => 'Una partnership che crea valore per entrambe le parti &ndash; senza costi.',
                'win_partner_tag'  => 'Per te come grossista',
                'win_partner_h3'   => 'Rafforza la fedeltà dei clienti',
                'win_p1'           => 'I tuoi clienti diventano più professionali &ndash; e ordinano di più da te',
                'win_p2'           => 'Posizionati come partner &ldquo;Più che ricambi&rdquo;',
                'win_p3'           => 'Valore aggiunto gratuito per il tuo portfolio &ndash; nessun investimento necessario',
                'win_p4'           => 'Presenza co-branded: &ldquo;Raccomandato da [Il tuo nome]&rdquo;',
                'win_p5'           => 'Commissione opzionale sugli upgrade Premium dei tuoi clienti',
                'win_customer_tag' => 'Per i clienti del tuo laboratorio',
                'win_customer_h3'  => 'Subito più professionali',
                'win_c1'           => 'Gestione digitale gratuita delle riparazioni &ndash; pronta all&rsquo;uso',
                'win_c2'           => 'Basta caos cartaceo &ndash; tutto digitale e ricercabile',
                'win_c3'           => 'Aspetto professionale verso i clienti finali',
                'win_c4'           => 'Il tracciamento riduce le chiamate &ldquo;Dov&rsquo;&egrave; il mio dispositivo?&rdquo; dell&rsquo;80%',
                'win_c5'           => 'Risparmia 30+ minuti al giorno con processi automatizzati',

                'visibility_title' => 'La tua visibilità in ogni laboratorio',
                'visibility_sub'   => 'Come partner, sei presente direttamente nella dashboard di ogni laboratorio come fornitore raccomandato &ndash; con logo, nome e link al tuo webshop.',
                'vis1_title'       => 'Visibile nella dashboard',
                'vis1_text'        => 'Ogni laboratorio vede il link del tuo webshop nel suo spazio di lavoro quotidiano &ndash; dove gestisce le riparazioni.',
                'vis2_title'       => 'Ordini diretti',
                'vis2_text'        => 'I laboratori possono aprire il tuo webshop con un clic &ndash; quando hanno bisogno di ricambi, sei subito lì.',
                'vis3_title'       => 'Copertura in crescita',
                'vis3_text'        => 'Più laboratori usano il Reparaturpass, più potenziali acquirenti vedono la tua offerta &ndash; automaticamente.',

                'models_title'     => 'Modelli di partnership',
                'models_sub'       => 'Flessibile e senza impegno &ndash; scegli quello che si adatta meglio.',
                'model1_title'     => 'Newsletter e Webshop',
                'model1_text'      => 'Menzione nella tua newsletter o banner nel tuo webshop. Sforzo minimo, copertura immediata.',
                'model1_d1'        => '30 min. di impegno una tantum',
                'model1_d2'        => 'Gratuito',
                'model2_badge'     => 'Raccomandato',
                'model2_title'     => 'Inserto nel pacco',
                'model2_text'      => 'Un piccolo volantino in ogni consegna. I tuoi clienti scoprono il Reparaturpass aprendo il pacco.',
                'model2_d1'        => 'Massima visibilità',
                'model2_d2'        => 'Forniamo i volantini gratuitamente',
                'model3_title'     => 'Co-Branded',
                'model3_text'      => 'Pagina partner personalizzata con il tuo logo. &ldquo;Raccomandato da [La tua azienda]&rdquo; nel Reparaturpass.',
                'model3_d1'        => 'Esclusivo per partner principali',
                'model3_d2'        => 'Revenue share possibile',
                'model4_badge'     => 'Nuovo',
                'model4_title'     => 'Widget Embed',
                'model4_text'      => 'Widget JavaScript pronto per il tuo webshop. Un pulsante che presenta il Reparaturpass ai tuoi clienti &ndash; nel tuo design.',
                'model4_d1'        => '1 riga di codice &ndash; subito live',
                'model4_d2'        => 'Dashboard propria con statistiche',

                'steps_title'      => 'Come funziona',
                'steps_sub'        => '4 semplici passi verso la partnership &ndash; senza contratti, senza obblighi.',
                'step1_title'      => 'Prova',
                'step1_text'       => 'Registrati e prova il Reparaturpass dal punto di vista del laboratorio.',
                'step2_title'      => 'Discuti',
                'step2_text'       => 'Parliamo di quale modello si adatta meglio a te.',
                'step3_title'      => 'Lancia',
                'step3_text'       => 'Forniamo banner, volantini o co-branding &ndash; tu li condividi con i tuoi clienti.',
                'step4_title'      => 'Profitto',
                'step4_text'       => 'Clienti più felici, fedeltà più forte &ndash; e revenue share opzionale.',

                'pricing_title'    => 'Prezzi per i laboratori',
                'pricing_sub'      => 'I tuoi clienti iniziano gratis. Premium solo quando necessario.',
                'price_free_tag'   => 'Gratuito',
                'price_free_val'   => '0&euro;',
                'price_per_month'  => '/ mese',
                'price_free_desc'  => 'Per piccoli laboratori',
                'price_f1'         => '50 moduli al mese',
                'price_f2'         => 'Modulo di riparazione personalizzato',
                'price_f3'         => 'Tracciamento clienti via QR',
                'price_f4'         => 'Dashboard e panoramica',
                'price_f5'         => 'Utilizzo illimitato',
                'price_f6'         => 'Sistema punti fedeltà incluso',
                'price_prem_tag'   => 'Premium',
                'price_prem_val'   => '39&euro;',
                'price_prem_desc'  => 'Per attività professionali',
                'price_p1'         => 'Moduli illimitati',
                'price_p2'         => 'Generazione fatture',
                'price_p3'         => 'Esportazione CSV/PDF',
                'price_p4'         => 'Supporto multi-sede',
                'price_p5'         => 'Modulo acquisti',
                'price_p6'         => 'Supporto prioritario',

                'cta_title'        => 'Pronti per una partnership?',
                'cta_text'         => 'Digitalizziamo insieme il settore delle riparazioni. Nessun rischio, nessuno sforzo &ndash; solo valore.',
                'cta_demo'         => 'Guarda la demo live',
                'cta_contact'      => 'Contattaci',
                'cta_mail_subject' => 'Partnership%20Reparaturpass',

                'footer_conf'      => 'Questo documento è riservato.',
                'print_title'      => 'Stampa come PDF',
            ],
        ];
    }

    public static function render() {
        $logo_url = PPV_PLUGIN_URL . 'assets/img/punktepass-repair-logo.svg';

        // Detect language: GET > Cookie > Browser Accept-Language > default DE
        $supported_langs = ['de', 'en', 'hu', 'ro', 'it'];
        $lang = null;
        $get_lang = $_GET['lang'] ?? $_GET['ppv_lang'] ?? null;
        if ($get_lang && in_array(strtolower($get_lang), $supported_langs, true)) {
            $lang = strtolower($get_lang);
            // Persist language choice in cookie
            @setcookie('ppv_lang', $lang, time() + 31536000, '/', '', is_ssl(), false);
            $_COOKIE['ppv_lang'] = $lang;
        } elseif (!empty($_COOKIE['ppv_lang']) && in_array(strtolower($_COOKIE['ppv_lang']), $supported_langs, true)) {
            $lang = strtolower($_COOKIE['ppv_lang']);
        }
        // Browser Accept-Language detection
        if (!$lang && !empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            preg_match_all('/([a-z]{2})(?:-[a-zA-Z]+)?(?:\s*;\s*q\s*=\s*([\d.]+))?/', strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE']), $m, PREG_SET_ORDER);
            $candidates = [];
            foreach ($m as $match) {
                $code = $match[1];
                $q = isset($match[2]) ? (float)$match[2] : 1.0;
                if (in_array($code, $supported_langs, true) && (!isset($candidates[$code]) || $q > $candidates[$code])) {
                    $candidates[$code] = $q;
                }
            }
            if ($candidates) {
                arsort($candidates);
                $lang = key($candidates);
            }
        }
        if (!$lang) $lang = 'en';

        $all = self::get_translations();
        $t = $all[$lang];
        // Build list of other languages for the switcher
        $other_langs = array_diff($supported_langs, [$lang]);

        ?><!DOCTYPE html>
<html lang="<?php echo $t['html_lang']; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $t['page_title']; ?></title>
    <meta name="robots" content="index, follow">
    <meta name="description" content="<?php echo esc_attr(html_entity_decode(strip_tags($t['hero_sub']), ENT_QUOTES, 'UTF-8')); ?>">
    <link rel="canonical" href="https://punktepass.de/formular/partner<?php echo $lang !== 'de' ? '?lang=' . $lang : ''; ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css">
    <?php echo PPV_SEO::get_favicon_links(); ?>
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        html { font-size: 16px; scroll-behavior: smooth; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            color: #1e293b;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }
        a { color: #667eea; text-decoration: none; }

        /* ── Print styles ── */
        @media print {
            body { background: #fff; font-size: 11pt; }
            .pp-no-print { display: none !important; }
            .pp-section { break-inside: avoid; page-break-inside: avoid; }
            .pp-hero-partner { padding: 40px 20px; min-height: auto; }
            .pp-partner-container { max-width: 100%; }
        }

        /* ── Animations ── */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(24px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeInScale {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        @keyframes slideRight {
            from { width: 0; }
            to { width: 100%; }
        }
        .pp-anim { opacity: 0; }
        .pp-anim.pp-visible { animation: fadeInUp 0.6s ease-out forwards; }
        .pp-anim-d1 { animation-delay: 0.1s; }
        .pp-anim-d2 { animation-delay: 0.2s; }
        .pp-anim-d3 { animation-delay: 0.3s; }
        .pp-anim-d4 { animation-delay: 0.4s; }
        .pp-anim-d5 { animation-delay: 0.5s; }
        .pp-anim-d6 { animation-delay: 0.6s; }
        .pp-anim-d7 { animation-delay: 0.7s; }

        /* ── Container ── */
        .pp-partner-container {
            max-width: 960px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* ── Language Switcher ── */
        .pp-partner-lang {
            position: absolute;
            top: 20px;
            right: 24px;
            z-index: 50;
        }
        .pp-partner-lang-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255,255,255,0.12);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 10px;
            padding: 7px 14px;
            font-size: 13px;
            font-weight: 600;
            color: rgba(255,255,255,0.9);
            cursor: pointer;
            transition: all 0.25s;
            font-family: inherit;
        }
        .pp-partner-lang-dropdown {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 6px;
            background: rgba(30,30,50,0.95);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 10px;
            overflow: hidden;
            min-width: 120px;
        }
        .pp-partner-lang.open .pp-partner-lang-dropdown { display: block; }
        .pp-partner-lang-item {
            display: block;
            padding: 8px 16px;
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: background 0.15s;
        }
        .pp-partner-lang-item:hover {
            background: rgba(255,255,255,0.1);
            color: #fff;
        }
        .pp-partner-lang-btn:hover {
            background: rgba(255,255,255,0.2);
        }
        .pp-partner-lang-btn i { font-size: 15px; }

        /* ── Hero ── */
        .pp-hero-partner {
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 30%, #4338ca 60%, #667eea 100%);
            padding: 60px 24px 72px;
            text-align: center;
            position: relative;
            overflow: hidden;
            min-height: 420px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .pp-hero-partner::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat;
        }
        .pp-hero-partner-inner {
            position: relative;
            z-index: 1;
            max-width: 680px;
        }
        .pp-hero-partner-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 100px;
            padding: 8px 20px;
            font-size: 13px;
            font-weight: 600;
            color: rgba(255,255,255,0.9);
            margin-bottom: 28px;
        }
        .pp-hero-partner-badge i { font-size: 16px; color: #fde047; }
        .pp-hero-partner h1 {
            color: #fff;
            font-size: 42px;
            font-weight: 900;
            line-height: 1.15;
            margin-bottom: 18px;
            letter-spacing: -1px;
        }
        .pp-hero-partner h1 span {
            background: linear-gradient(135deg, #fde047, #facc15);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .pp-hero-partner-sub {
            color: rgba(255,255,255,0.8);
            font-size: 18px;
            max-width: 540px;
            margin: 0 auto;
            line-height: 1.7;
        }
        .pp-hero-partner-logo {
            height: 38px;
            margin-bottom: 20px;
            filter: brightness(0) invert(1);
        }

        /* ── Section base ── */
        .pp-section {
            padding: 64px 0;
        }
        .pp-section-alt {
            background: #fff;
        }
        .pp-section-title {
            font-size: 28px;
            font-weight: 800;
            color: #1e293b;
            text-align: center;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }
        .pp-section-sub {
            font-size: 15px;
            color: #64748b;
            text-align: center;
            max-width: 560px;
            margin: 0 auto 40px;
        }

        /* ── Problem Section ── */
        .pp-problem-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .pp-problem-card {
            background: #fff;
            border-radius: 16px;
            padding: 28px 24px;
            border: 1px solid #e2e8f0;
            display: flex;
            gap: 16px;
            align-items: flex-start;
        }
        .pp-problem-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }
        .pp-problem-icon.red { background: #fef2f2; color: #ef4444; }
        .pp-problem-icon.amber { background: #fffbeb; color: #f59e0b; }
        .pp-problem-card h4 {
            font-size: 14px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 4px;
        }
        .pp-problem-card p {
            font-size: 13px;
            color: #64748b;
            line-height: 1.5;
            margin: 0;
        }

        /* ── Solution Section ── */
        .pp-solution-hero {
            background: linear-gradient(135deg, #f0f2ff, #e8ecff);
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            margin-bottom: 32px;
            border: 1px solid #ddd6fe;
        }
        .pp-solution-hero h3 {
            font-size: 22px;
            font-weight: 800;
            color: #4338ca;
            margin-bottom: 8px;
        }
        .pp-solution-hero p {
            font-size: 15px;
            color: #6366f1;
            max-width: 500px;
            margin: 0 auto;
        }
        .pp-solution-features {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }
        .pp-sol-feat {
            background: #fff;
            border-radius: 14px;
            padding: 24px 18px;
            text-align: center;
            border: 1px solid #e2e8f0;
            transition: all 0.3s;
        }
        .pp-sol-feat:hover {
            border-color: #c7d2fe;
            box-shadow: 0 4px 20px rgba(99,102,241,0.08);
        }
        .pp-sol-feat-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin: 0 auto 12px;
        }
        .pp-sol-feat-icon.blue { background: linear-gradient(135deg, #eff6ff, #dbeafe); color: #3b82f6; }
        .pp-sol-feat-icon.green { background: linear-gradient(135deg, #f0fdf4, #dcfce7); color: #22c55e; }
        .pp-sol-feat-icon.purple { background: linear-gradient(135deg, #f5f3ff, #ede9fe); color: #8b5cf6; }
        .pp-sol-feat-icon.amber { background: linear-gradient(135deg, #fffbeb, #fef3c7); color: #f59e0b; }
        .pp-sol-feat-icon.rose { background: linear-gradient(135deg, #fff1f2, #fce7f3); color: #f43f5e; }
        .pp-sol-feat-icon.teal { background: linear-gradient(135deg, #f0fdfa, #ccfbf1); color: #14b8a6; }
        .pp-sol-feat-icon.yellow { background: linear-gradient(135deg, #fefce8, #fef9c3); color: #eab308; }
        .pp-sol-feat h4 {
            font-size: 14px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 4px;
        }
        .pp-sol-feat p {
            font-size: 12px;
            color: #64748b;
            line-height: 1.4;
            margin: 0;
        }

        /* ── Stats bar ── */
        .pp-stats-bar {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin: 48px 0 0;
        }
        .pp-stat-card {
            background: #fff;
            border-radius: 14px;
            padding: 24px 16px;
            text-align: center;
            border: 1px solid #e2e8f0;
        }
        .pp-stat-val {
            font-size: 32px;
            font-weight: 900;
            color: #4338ca;
            line-height: 1;
            margin-bottom: 4px;
        }
        .pp-stat-label {
            font-size: 12px;
            color: #64748b;
            font-weight: 500;
        }

        /* ── Win-Win Section ── */
        .pp-win-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }
        .pp-win-card {
            border-radius: 20px;
            padding: 36px 28px;
            position: relative;
            overflow: hidden;
        }
        .pp-win-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 120px;
            height: 120px;
            border-radius: 50%;
            filter: blur(50px);
            opacity: 0.3;
        }
        .pp-win-card.partner {
            background: linear-gradient(135deg, #1e1b4b, #312e81);
            color: #fff;
        }
        .pp-win-card.partner::before { background: #818cf8; }
        .pp-win-card.customer {
            background: linear-gradient(135deg, #065f46, #047857);
            color: #fff;
        }
        .pp-win-card.customer::before { background: #34d399; }
        .pp-win-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 5px 12px;
            border-radius: 100px;
            margin-bottom: 18px;
            position: relative;
        }
        .pp-win-card.partner .pp-win-tag { background: rgba(255,255,255,0.15); color: #c7d2fe; }
        .pp-win-card.customer .pp-win-tag { background: rgba(255,255,255,0.15); color: #a7f3d0; }
        .pp-win-card h3 {
            font-size: 20px;
            font-weight: 800;
            margin-bottom: 16px;
            position: relative;
        }
        .pp-win-list {
            list-style: none;
            padding: 0;
            position: relative;
        }
        .pp-win-list li {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 12px;
            opacity: 0.9;
        }
        .pp-win-list li i {
            font-size: 16px;
            margin-top: 2px;
            flex-shrink: 0;
        }
        .pp-win-card.partner .pp-win-list li i { color: #a5b4fc; }
        .pp-win-card.customer .pp-win-list li i { color: #6ee7b7; }

        /* ── Visibility Boost Section ── */
        .pp-section-visibility {
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 40%, #4338ca 100%);
            padding: 72px 0;
        }
        .pp-vis-banner {
            text-align: center;
        }
        .pp-vis-icon-wrap {
            width: 64px;
            height: 64px;
            border-radius: 20px;
            background: rgba(255,255,255,0.12);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 28px;
            color: #fde047;
        }
        .pp-vis-title {
            font-size: 28px;
            font-weight: 900;
            color: #fff;
            margin-bottom: 12px;
            letter-spacing: -0.5px;
        }
        .pp-vis-sub {
            font-size: 15px;
            color: rgba(255,255,255,0.75);
            max-width: 600px;
            margin: 0 auto 40px;
            line-height: 1.7;
        }
        .pp-vis-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        .pp-vis-card {
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 16px;
            padding: 28px 22px;
            text-align: center;
            transition: all 0.3s;
        }
        .pp-vis-card:hover {
            background: rgba(255,255,255,0.14);
            border-color: rgba(255,255,255,0.25);
            transform: translateY(-4px);
        }
        .pp-vis-card-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: rgba(253,224,71,0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: #fde047;
            margin: 0 auto 14px;
        }
        .pp-vis-card h4 {
            font-size: 15px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 6px;
        }
        .pp-vis-card p {
            font-size: 13px;
            color: rgba(255,255,255,0.7);
            line-height: 1.5;
            margin: 0;
        }
        @media (max-width: 768px) {
            .pp-vis-grid { grid-template-columns: 1fr; }
            .pp-section-visibility { padding: 48px 0; }
            .pp-vis-title { font-size: 22px; }
        }

        /* ── Partnership Models ── */
        .pp-model-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        .pp-model-card {
            background: #fff;
            border-radius: 16px;
            padding: 32px 24px;
            border: 1px solid #e2e8f0;
            text-align: center;
            transition: all 0.3s;
            position: relative;
        }
        .pp-model-card:hover {
            border-color: #c7d2fe;
            transform: translateY(-4px);
            box-shadow: 0 12px 36px rgba(99,102,241,0.1);
        }
        .pp-model-card.recommended {
            border-color: #818cf8;
            box-shadow: 0 4px 20px rgba(99,102,241,0.12);
        }
        .pp-model-badge {
            position: absolute;
            top: -10px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, #667eea, #4338ca);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            padding: 4px 14px;
            border-radius: 100px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .pp-model-icon {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin: 0 auto 16px;
            background: linear-gradient(135deg, #f0f2ff, #e8ecff);
            color: #4338ca;
        }
        .pp-model-card h4 {
            font-size: 16px;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 8px;
        }
        .pp-model-card p {
            font-size: 13px;
            color: #64748b;
            line-height: 1.5;
            margin-bottom: 16px;
        }
        .pp-model-detail {
            font-size: 12px;
            color: #94a3b8;
            padding-top: 16px;
            border-top: 1px solid #f1f5f9;
            line-height: 1.5;
        }

        /* ── How it works ── */
        .pp-steps-partner {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            counter-reset: step;
        }
        .pp-step-card {
            background: #fff;
            border-radius: 14px;
            padding: 28px 18px;
            text-align: center;
            border: 1px solid #e2e8f0;
            position: relative;
        }
        .pp-step-num {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #4338ca);
            color: #fff;
            font-size: 15px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 14px;
        }
        .pp-step-card h4 {
            font-size: 14px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 6px;
        }
        .pp-step-card p {
            font-size: 12px;
            color: #64748b;
            line-height: 1.4;
            margin: 0;
        }

        /* ── Pricing preview ── */
        .pp-pricing-partner {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            max-width: 640px;
            margin: 0 auto;
        }
        .pp-price-box {
            background: #fff;
            border-radius: 16px;
            padding: 32px 28px;
            text-align: center;
            border: 1px solid #e2e8f0;
        }
        .pp-price-box.premium {
            border-color: #818cf8;
            background: linear-gradient(135deg, #fefeff, #f8f7ff);
        }
        .pp-price-tag {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #64748b;
            margin-bottom: 8px;
        }
        .pp-price-val {
            font-size: 36px;
            font-weight: 900;
            color: #1e293b;
            line-height: 1;
            margin-bottom: 4px;
        }
        .pp-price-val span {
            font-size: 15px;
            font-weight: 500;
            color: #94a3b8;
        }
        .pp-price-desc {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 16px;
        }
        .pp-price-list {
            list-style: none;
            padding: 0;
            text-align: left;
        }
        .pp-price-list li {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #475569;
            margin-bottom: 8px;
        }
        .pp-price-list li i { color: #22c55e; font-size: 15px; }

        /* ── CTA Section ── */
        .pp-cta-section {
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 40%, #4338ca 100%);
            padding: 72px 24px;
            text-align: center;
        }
        .pp-cta-section h2 {
            color: #fff;
            font-size: 32px;
            font-weight: 900;
            margin-bottom: 12px;
            letter-spacing: -0.5px;
        }
        .pp-cta-section p {
            color: rgba(255,255,255,0.75);
            font-size: 16px;
            max-width: 480px;
            margin: 0 auto 32px;
        }
        .pp-cta-buttons {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .pp-cta-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 32px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.3s;
        }
        .pp-cta-btn.primary {
            background: #fff;
            color: #4338ca;
        }
        .pp-cta-btn.primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.2);
            text-decoration: none;
        }
        .pp-cta-btn.secondary {
            background: rgba(255,255,255,0.1);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.2);
        }
        .pp-cta-btn.secondary:hover {
            background: rgba(255,255,255,0.2);
            text-decoration: none;
        }

        /* ── Footer ── */
        .pp-partner-footer {
            text-align: center;
            padding: 28px 24px;
            font-size: 13px;
            color: #94a3b8;
        }
        .pp-partner-footer a { color: #667eea; }

        /* ── Print button ── */
        .pp-print-btn {
            position: fixed;
            bottom: 24px;
            right: 24px;
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #4338ca);
            color: #fff;
            border: none;
            font-size: 22px;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(99,102,241,0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            z-index: 100;
        }
        .pp-print-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 28px rgba(99,102,241,0.5);
        }

        /* ── Responsive ── */
        @media (max-width: 768px) {
            .pp-hero-partner { padding: 40px 20px 56px; min-height: auto; }
            .pp-hero-partner h1 { font-size: 28px; }
            .pp-hero-partner-sub { font-size: 15px; }
            .pp-section { padding: 48px 0; }
            .pp-section-title { font-size: 22px; }
            .pp-problem-grid { grid-template-columns: 1fr; }
            .pp-solution-features { grid-template-columns: 1fr 1fr; }
            .pp-stats-bar { grid-template-columns: 1fr 1fr; }
            .pp-win-grid { grid-template-columns: 1fr; }
            .pp-model-grid { grid-template-columns: 1fr; }
            .pp-steps-partner { grid-template-columns: 1fr 1fr; }
            .pp-pricing-partner { grid-template-columns: 1fr; }
            .pp-cta-section h2 { font-size: 24px; }
            .pp-partner-lang { top: 12px; right: 16px; }
        }
        @media (max-width: 480px) {
            .pp-hero-partner h1 { font-size: 24px; }
            .pp-solution-features { grid-template-columns: 1fr; }
            .pp-stats-bar { grid-template-columns: 1fr 1fr; }
            .pp-steps-partner { grid-template-columns: 1fr; }
            .pp-solution-hero { padding: 28px 20px; }
        }
    </style>
</head>
<body>

<!-- ============ HERO ============ -->
<div class="pp-hero-partner">
    <!-- Language Switcher -->
    <div class="pp-partner-lang pp-no-print">
        <span class="pp-partner-lang-btn" onclick="this.parentNode.classList.toggle('open')">
            <i class="ri-global-line"></i>
            <?php echo strtoupper($lang); ?>
            <i class="ri-arrow-down-s-line" style="font-size:14px;margin-left:-2px"></i>
        </span>
        <div class="pp-partner-lang-dropdown">
            <?php
            $lang_names = ['de'=>'Deutsch','en'=>'English','hu'=>'Magyar','ro'=>'Română','it'=>'Italiano'];
            foreach ($other_langs as $ol): ?>
            <a href="?lang=<?php echo $ol; ?>" class="pp-partner-lang-item"><?php echo ($lang_names[$ol] ?? strtoupper($ol)); ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="pp-hero-partner-inner">
        <img src="<?php echo esc_url($logo_url); ?>" alt="Reparaturpass" class="pp-hero-partner-logo">
        <div class="pp-hero-partner-badge">
            <i class="ri-handshake-line"></i>
            <?php echo $t['badge']; ?>
        </div>
        <h1><?php echo $t['hero_h1']; ?></h1>
        <p class="pp-hero-partner-sub"><?php echo $t['hero_sub']; ?></p>
    </div>
</div>

<!-- ============ PROBLEM ============ -->
<div class="pp-section pp-section-alt">
    <div class="pp-partner-container">
        <h2 class="pp-section-title pp-anim"><?php echo $t['problem_title']; ?></h2>
        <p class="pp-section-sub pp-anim pp-anim-d1"><?php echo $t['problem_sub']; ?></p>

        <div class="pp-problem-grid">
            <div class="pp-problem-card pp-anim pp-anim-d1">
                <div class="pp-problem-icon red"><i class="ri-file-paper-line"></i></div>
                <div>
                    <h4><?php echo $t['prob1_title']; ?></h4>
                    <p><?php echo $t['prob1_text']; ?></p>
                </div>
            </div>
            <div class="pp-problem-card pp-anim pp-anim-d2">
                <div class="pp-problem-icon amber"><i class="ri-time-line"></i></div>
                <div>
                    <h4><?php echo $t['prob2_title']; ?></h4>
                    <p><?php echo $t['prob2_text']; ?></p>
                </div>
            </div>
            <div class="pp-problem-card pp-anim pp-anim-d3">
                <div class="pp-problem-icon red"><i class="ri-customer-service-line"></i></div>
                <div>
                    <h4><?php echo $t['prob3_title']; ?></h4>
                    <p><?php echo $t['prob3_text']; ?></p>
                </div>
            </div>
            <div class="pp-problem-card pp-anim pp-anim-d4">
                <div class="pp-problem-icon amber"><i class="ri-money-euro-circle-line"></i></div>
                <div>
                    <h4><?php echo $t['prob4_title']; ?></h4>
                    <p><?php echo $t['prob4_text']; ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============ SOLUTION ============ -->
<div class="pp-section">
    <div class="pp-partner-container">
        <h2 class="pp-section-title pp-anim"><?php echo $t['solution_title']; ?></h2>
        <p class="pp-section-sub pp-anim pp-anim-d1"><?php echo $t['solution_sub']; ?></p>

        <div class="pp-solution-hero pp-anim pp-anim-d2">
            <h3><i class="ri-smartphone-line"></i> <?php echo $t['sol_hero_title']; ?></h3>
            <p><?php echo $t['sol_hero_text']; ?></p>
        </div>

        <div class="pp-solution-features">
            <div class="pp-sol-feat pp-anim pp-anim-d1">
                <div class="pp-sol-feat-icon blue"><i class="ri-smartphone-line"></i></div>
                <h4><?php echo $t['feat1_title']; ?></h4>
                <p><?php echo $t['feat1_text']; ?></p>
            </div>
            <div class="pp-sol-feat pp-anim pp-anim-d2">
                <div class="pp-sol-feat-icon green"><i class="ri-file-text-line"></i></div>
                <h4><?php echo $t['feat2_title']; ?></h4>
                <p><?php echo $t['feat2_text']; ?></p>
            </div>
            <div class="pp-sol-feat pp-anim pp-anim-d3">
                <div class="pp-sol-feat-icon purple"><i class="ri-bar-chart-2-line"></i></div>
                <h4><?php echo $t['feat3_title']; ?></h4>
                <p><?php echo $t['feat3_text']; ?></p>
            </div>
            <div class="pp-sol-feat pp-anim pp-anim-d4">
                <div class="pp-sol-feat-icon amber"><i class="ri-qr-code-line"></i></div>
                <h4><?php echo $t['feat4_title']; ?></h4>
                <p><?php echo $t['feat4_text']; ?></p>
            </div>
            <div class="pp-sol-feat pp-anim pp-anim-d5">
                <div class="pp-sol-feat-icon rose"><i class="ri-team-line"></i></div>
                <h4><?php echo $t['feat5_title']; ?></h4>
                <p><?php echo $t['feat5_text']; ?></p>
            </div>
            <div class="pp-sol-feat pp-anim pp-anim-d6">
                <div class="pp-sol-feat-icon teal"><i class="ri-building-2-line"></i></div>
                <h4><?php echo $t['feat6_title']; ?></h4>
                <p><?php echo $t['feat6_text']; ?></p>
            </div>
            <div class="pp-sol-feat pp-anim pp-anim-d7">
                <div class="pp-sol-feat-icon yellow"><i class="ri-star-smile-line"></i></div>
                <h4><?php echo $t['feat7_title']; ?></h4>
                <p><?php echo $t['feat7_text']; ?></p>
            </div>
        </div>

        <!-- Stats -->
        <div class="pp-stats-bar">
            <div class="pp-stat-card pp-anim pp-anim-d1">
                <div class="pp-stat-val"><?php echo $t['stat1_val']; ?></div>
                <div class="pp-stat-label"><?php echo $t['stat1_label']; ?></div>
            </div>
            <div class="pp-stat-card pp-anim pp-anim-d2">
                <div class="pp-stat-val"><?php echo $t['stat2_val']; ?></div>
                <div class="pp-stat-label"><?php echo $t['stat2_label']; ?></div>
            </div>
            <div class="pp-stat-card pp-anim pp-anim-d3">
                <div class="pp-stat-val"><?php echo $t['stat3_val']; ?></div>
                <div class="pp-stat-label"><?php echo $t['stat3_label']; ?></div>
            </div>
            <div class="pp-stat-card pp-anim pp-anim-d4">
                <div class="pp-stat-val"><?php echo $t['stat4_val']; ?></div>
                <div class="pp-stat-label"><?php echo $t['stat4_label']; ?></div>
            </div>
        </div>
    </div>
</div>

<!-- ============ WIN-WIN ============ -->
<div class="pp-section pp-section-alt">
    <div class="pp-partner-container">
        <h2 class="pp-section-title pp-anim"><?php echo $t['winwin_title']; ?></h2>
        <p class="pp-section-sub pp-anim pp-anim-d1"><?php echo $t['winwin_sub']; ?></p>

        <div class="pp-win-grid">
            <div class="pp-win-card partner pp-anim pp-anim-d2">
                <div class="pp-win-tag"><i class="ri-building-line"></i> <?php echo $t['win_partner_tag']; ?></div>
                <h3><?php echo $t['win_partner_h3']; ?></h3>
                <ul class="pp-win-list">
                    <li><i class="ri-check-line"></i> <?php echo $t['win_p1']; ?></li>
                    <li><i class="ri-check-line"></i> <?php echo $t['win_p2']; ?></li>
                    <li><i class="ri-check-line"></i> <?php echo $t['win_p3']; ?></li>
                    <li><i class="ri-check-line"></i> <?php echo $t['win_p4']; ?></li>
                    <li><i class="ri-check-line"></i> <?php echo $t['win_p5']; ?></li>
                </ul>
            </div>
            <div class="pp-win-card customer pp-anim pp-anim-d3">
                <div class="pp-win-tag"><i class="ri-store-2-line"></i> <?php echo $t['win_customer_tag']; ?></div>
                <h3><?php echo $t['win_customer_h3']; ?></h3>
                <ul class="pp-win-list">
                    <li><i class="ri-check-line"></i> <?php echo $t['win_c1']; ?></li>
                    <li><i class="ri-check-line"></i> <?php echo $t['win_c2']; ?></li>
                    <li><i class="ri-check-line"></i> <?php echo $t['win_c3']; ?></li>
                    <li><i class="ri-check-line"></i> <?php echo $t['win_c4']; ?></li>
                    <li><i class="ri-check-line"></i> <?php echo $t['win_c5']; ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- ============ VISIBILITY BOOST ============ -->
<div class="pp-section pp-section-visibility">
    <div class="pp-partner-container">
        <div class="pp-vis-banner pp-anim">
            <div class="pp-vis-icon-wrap">
                <i class="ri-eye-line"></i>
            </div>
            <h2 class="pp-vis-title"><?php echo $t['visibility_title']; ?></h2>
            <p class="pp-vis-sub"><?php echo $t['visibility_sub']; ?></p>

            <div class="pp-vis-grid">
                <div class="pp-vis-card pp-anim pp-anim-d1">
                    <div class="pp-vis-card-icon"><i class="ri-layout-4-line"></i></div>
                    <h4><?php echo $t['vis1_title']; ?></h4>
                    <p><?php echo $t['vis1_text']; ?></p>
                </div>
                <div class="pp-vis-card pp-anim pp-anim-d2">
                    <div class="pp-vis-card-icon"><i class="ri-shopping-cart-2-line"></i></div>
                    <h4><?php echo $t['vis2_title']; ?></h4>
                    <p><?php echo $t['vis2_text']; ?></p>
                </div>
                <div class="pp-vis-card pp-anim pp-anim-d3">
                    <div class="pp-vis-card-icon"><i class="ri-line-chart-line"></i></div>
                    <h4><?php echo $t['vis3_title']; ?></h4>
                    <p><?php echo $t['vis3_text']; ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============ PARTNERSHIP MODELS ============ -->
<div class="pp-section">
    <div class="pp-partner-container">
        <h2 class="pp-section-title pp-anim"><?php echo $t['models_title']; ?></h2>
        <p class="pp-section-sub pp-anim pp-anim-d1"><?php echo $t['models_sub']; ?></p>

        <div class="pp-model-grid">
            <div class="pp-model-card pp-anim pp-anim-d1">
                <div class="pp-model-icon"><i class="ri-mail-send-line"></i></div>
                <h4><?php echo $t['model1_title']; ?></h4>
                <p><?php echo $t['model1_text']; ?></p>
                <div class="pp-model-detail">
                    <i class="ri-time-line"></i> <?php echo $t['model1_d1']; ?><br>
                    <i class="ri-money-euro-circle-line"></i> <?php echo $t['model1_d2']; ?>
                </div>
            </div>
            <div class="pp-model-card recommended pp-anim pp-anim-d2">
                <div class="pp-model-badge"><?php echo $t['model2_badge']; ?></div>
                <div class="pp-model-icon"><i class="ri-gift-line"></i></div>
                <h4><?php echo $t['model2_title']; ?></h4>
                <p><?php echo $t['model2_text']; ?></p>
                <div class="pp-model-detail">
                    <i class="ri-eye-line"></i> <?php echo $t['model2_d1']; ?><br>
                    <i class="ri-money-euro-circle-line"></i> <?php echo $t['model2_d2']; ?>
                </div>
            </div>
            <div class="pp-model-card pp-anim pp-anim-d3">
                <div class="pp-model-icon"><i class="ri-vip-crown-line"></i></div>
                <h4><?php echo $t['model3_title']; ?></h4>
                <p><?php echo $t['model3_text']; ?></p>
                <div class="pp-model-detail">
                    <i class="ri-star-line"></i> <?php echo $t['model3_d1']; ?><br>
                    <i class="ri-money-euro-circle-line"></i> <?php echo $t['model3_d2']; ?>
                </div>
            </div>
            <div class="pp-model-card recommended pp-anim pp-anim-d4">
                <div class="pp-model-badge"><?php echo $t['model4_badge']; ?></div>
                <div class="pp-model-icon"><i class="ri-code-s-slash-line"></i></div>
                <h4><?php echo $t['model4_title']; ?></h4>
                <p><?php echo $t['model4_text']; ?></p>
                <div class="pp-model-detail">
                    <i class="ri-flashlight-line"></i> <?php echo $t['model4_d1']; ?><br>
                    <i class="ri-bar-chart-box-line"></i> <?php echo $t['model4_d2']; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============ HOW IT WORKS ============ -->
<div class="pp-section pp-section-alt">
    <div class="pp-partner-container">
        <h2 class="pp-section-title pp-anim"><?php echo $t['steps_title']; ?></h2>
        <p class="pp-section-sub pp-anim pp-anim-d1"><?php echo $t['steps_sub']; ?></p>

        <div class="pp-steps-partner">
            <div class="pp-step-card pp-anim pp-anim-d1">
                <div class="pp-step-num">1</div>
                <h4><?php echo $t['step1_title']; ?></h4>
                <p><?php echo $t['step1_text']; ?></p>
            </div>
            <div class="pp-step-card pp-anim pp-anim-d2">
                <div class="pp-step-num">2</div>
                <h4><?php echo $t['step2_title']; ?></h4>
                <p><?php echo $t['step2_text']; ?></p>
            </div>
            <div class="pp-step-card pp-anim pp-anim-d3">
                <div class="pp-step-num">3</div>
                <h4><?php echo $t['step3_title']; ?></h4>
                <p><?php echo $t['step3_text']; ?></p>
            </div>
            <div class="pp-step-card pp-anim pp-anim-d4">
                <div class="pp-step-num">4</div>
                <h4><?php echo $t['step4_title']; ?></h4>
                <p><?php echo $t['step4_text']; ?></p>
            </div>
        </div>
    </div>
</div>

<!-- ============ PRICING PREVIEW ============ -->
<div class="pp-section">
    <div class="pp-partner-container">
        <h2 class="pp-section-title pp-anim"><?php echo $t['pricing_title']; ?></h2>
        <p class="pp-section-sub pp-anim pp-anim-d1"><?php echo $t['pricing_sub']; ?></p>

        <div class="pp-pricing-partner">
            <div class="pp-price-box pp-anim pp-anim-d1">
                <div class="pp-price-tag"><?php echo $t['price_free_tag']; ?></div>
                <div class="pp-price-val"><?php echo $t['price_free_val']; ?> <span><?php echo $t['price_per_month']; ?></span></div>
                <div class="pp-price-desc"><?php echo $t['price_free_desc']; ?></div>
                <ul class="pp-price-list">
                    <li><i class="ri-check-line"></i> <?php echo $t['price_f1']; ?></li>
                    <li><i class="ri-check-line"></i> <?php echo $t['price_f2']; ?></li>
                    <li><i class="ri-check-line"></i> <?php echo $t['price_f3']; ?></li>
                    <li><i class="ri-check-line"></i> <?php echo $t['price_f4']; ?></li>
                    <li><i class="ri-check-line"></i> <?php echo $t['price_f5']; ?></li>
                    <li><i class="ri-check-line"></i> <?php echo $t['price_f6']; ?></li>
                </ul>
            </div>
            <div class="pp-price-box premium pp-anim pp-anim-d2">
                <div class="pp-price-tag"><?php echo $t['price_prem_tag']; ?></div>
                <div class="pp-price-val"><?php echo $t['price_prem_val']; ?> <span><?php echo $t['price_per_month']; ?></span></div>
                <div class="pp-price-desc"><?php echo $t['price_prem_desc']; ?></div>
                <ul class="pp-price-list">
                    <li><i class="ri-check-line"></i> <?php echo $t['price_p1']; ?></li>
                    <li><i class="ri-check-line"></i> <?php echo $t['price_p2']; ?></li>
                    <li><i class="ri-check-line"></i> <?php echo $t['price_p3']; ?></li>
                    <li><i class="ri-check-line"></i> <?php echo $t['price_p4']; ?></li>
                    <li><i class="ri-check-line"></i> <?php echo $t['price_p5']; ?></li>
                    <li><i class="ri-check-line"></i> <?php echo $t['price_p6']; ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- ============ CTA ============ -->
<div class="pp-cta-section pp-no-print">
    <h2><?php echo $t['cta_title']; ?></h2>
    <p><?php echo $t['cta_text']; ?></p>
    <div class="pp-cta-buttons">
        <a href="/formular" class="pp-cta-btn primary">
            <i class="ri-play-circle-line"></i> <?php echo $t['cta_demo']; ?>
        </a>
        <a href="mailto:info@punktepass.com?subject=<?php echo $t['cta_mail_subject']; ?>" class="pp-cta-btn secondary">
            <i class="ri-mail-line"></i> <?php echo $t['cta_contact']; ?>
        </a>
    </div>
</div>

<!-- ============ FOOTER ============ -->
<div class="pp-partner-footer">
    &copy; <?php echo date('Y'); ?> PunktePass &middot; <a href="/formular">punktepass.de/formular</a>
    &middot; <?php echo $t['footer_conf']; ?>
</div>

<!-- Print Button -->
<button class="pp-print-btn pp-no-print" onclick="window.print()" title="<?php echo $t['print_title']; ?>">
    <i class="ri-printer-line"></i>
</button>

<!-- Scroll animations -->
<script>
(function(){
    var els = document.querySelectorAll('.pp-anim');
    if (!('IntersectionObserver' in window)) {
        els.forEach(function(e){ e.classList.add('pp-visible'); });
        return;
    }
    var io = new IntersectionObserver(function(entries){
        entries.forEach(function(entry){
            if (entry.isIntersecting) {
                entry.target.classList.add('pp-visible');
                io.unobserve(entry.target);
            }
        });
    }, { threshold: 0.15 });
    els.forEach(function(e){ io.observe(e); });
})();
</script>

</body>
</html>
        <?php
        exit;
    }
}
