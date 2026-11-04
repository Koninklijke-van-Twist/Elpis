<?php

/**
 * Constants
 */

const FLAG_SVGS = [
    'nl' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 900 600"><rect width="900" height="600" fill="#AE1C28"/><rect width="900" height="400" fill="#fff"/><rect width="900" height="200" fill="#fff"/><rect width="900" height="200" y="0" fill="#AE1C28"/><rect width="900" height="200" y="200" fill="#fff"/><rect width="900" height="200" y="400" fill="#21468B"/></svg>',
    'en' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 60 40"><clipPath id="a"><path d="M0 0v40h60V0z"/></clipPath><clipPath id="b"><path d="M30 20h30v20zv20H0zH0V0zV0h30z"/></clipPath><g clip-path="url(#a)"><path d="M0 0v40h60V0z" fill="#012169"/><path d="M0 0l60 40m0-40L0 40" stroke="#fff" stroke-width="8"/><path d="M0 0l60 40m0-40L0 40" clip-path="url(#b)" stroke="#C8102E" stroke-width="5"/><path d="M30 0v40M0 20h60" stroke="#fff" stroke-width="13"/><path d="M30 0v40M0 20h60" stroke="#C8102E" stroke-width="8"/></g></svg>',
    'de' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 5 3"><rect width="5" height="3" y="0" fill="#000"/><rect width="5" height="2" y="1" fill="#D00"/><rect width="5" height="1" y="2" fill="#FFCE00"/></svg>',
    'fr' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 900 600"><rect width="900" height="600" fill="#ED2939"/><rect width="600" height="600" fill="#fff"/><rect width="300" height="600" fill="#002395"/></svg>',
];

const SUPPORTED_LANGUAGES = [
    'nl' => ['flag' => '🇳🇱', 'label' => 'Nederlands'],
    'en' => ['flag' => '🇬🇧', 'label' => 'English'],
    'de' => ['flag' => '🇩🇪', 'label' => 'Deutsch'],
    'fr' => ['flag' => '🇫🇷', 'label' => 'Français'],
];

const LOCALE_BY_LANG = [
    'nl' => 'nl-NL',
    'en' => 'en-GB',
    'de' => 'de-DE',
    'fr' => 'fr-FR',
];

const TRANSLATIONS = [
    'nl' => [
        'lang.menu_aria' => 'Taal kiezen',
        'lang.switch_to' => 'Schakel naar %s',
        'app.title' => 'Monitor Ontvangstrapport',
        'elpis.hero.title' => 'Monitor Ontvangstrapport',
        'elpis.hero.subtitle' => 'Bekijk inkoopplanningsregels per project en projectmanager.',
        'elpis.label.company' => 'Bedrijf',
        'elpis.label.manager' => 'Projectmanager',
        'elpis.manager.all' => 'Iedereen',
        'elpis.section.projects' => 'Projecten',
        'elpis.label.search' => 'Zoeken',
        'elpis.placeholder.search' => 'Projectnr. of omschrijving',
        'elpis.label.line_search' => 'Zoeken in regels',
        'elpis.placeholder.line_search' => 'Werkorder, artikel of omschrijving',
        'elpis.empty.search' => 'Geen projecten gevonden voor deze zoekopdracht.',
        'elpis.meta.manager' => 'Projectmanager',
        'elpis.col.workorder' => 'Werkorder',
        'elpis.col.item' => 'Artikel',
        'elpis.col.description' => 'Omschrijving',
        'elpis.col.to_order' => 'Te bestellen',
        'elpis.col.ordered' => 'Besteld',
        'elpis.col.outstanding' => 'Openstaand',
        'elpis.col.received' => 'Ontvangen',
        'elpis.col.picked' => 'Gepicked',
        'elpis.tip.picked' => '%s gepicked, %s nodig.',
        'elpis.col.line_status' => 'Status',
        'elpis.col.expected_receipt' => 'Verwachte ontvangst',
        'elpis.receipt.on_stock' => 'Op voorraad',
        'elpis.receipt.missing' => 'Datum ontbreekt',
        'elpis.receipt.stock' => 'Uit voorraad',
        'elpis.receipt.partial' => 'Deels op voorraad, verwacht %s',
        'elpis.receipt.partial_missing' => 'Deels op voorraad, datum ontbreekt',
        'elpis.receipt.uncovered' => 'Verwacht %s, rest niet gedekt',
        'elpis.receipt.partial_uncovered' => 'Deels op voorraad, verwacht %s, rest niet gedekt',
        'elpis.summary.execution_from' => 'Uitvoering plannen vanaf',
        'elpis.summary.direct' => 'direct',
        'elpis.summary.missing' => 'datum ontbreekt',
        'elpis.empty.managers' => 'Geen projectmanagers gevonden',
        'elpis.empty.projects' => 'Geen projecten gevonden voor deze projectmanager.',
        'elpis.empty.lines' => 'Geen artikelregels voor dit project.',
        'elpis.error.load_failed' => 'Gegevens ophalen mislukt. Probeer het later opnieuw.',
        'elpis.loader.wait' => 'Even geduld...',
        'elpis.loader.loading' => 'Gegevens ophalen uit Business Central',
        'elpis.btn.expand_all' => 'Alles openklappen',
        'elpis.btn.collapse_all' => 'Alles dichtklappen',
        'elpis.label.status_filter' => 'Status',
        'elpis.link.copy' => 'Kopieer link naar dit project',
        'elpis.link.copied' => 'Link gekopieerd',
        'elpis.link.copy_failed' => 'Kopiëren mislukt',
        'elpis.col.planning_date' => 'Planningsdatum',
        'elpis.col.stock' => 'Voorraad',
        'elpis.receipt.already' => 'Reeds ontvangen (%s)',
        'elpis.receipt.already_nodate' => 'Reeds ontvangen',
        'elpis.receipt.to_order_suffix' => '%s nog te bestellen',
        'elpis.receipt.late' => 'Te laat',
        'elpis.status.return' => 'Retour / n.v.t.',
        'elpis.status.closed' => 'Afgesloten',
        'elpis.status.picked' => 'Gepicked',
        'elpis.status.late' => 'Te laat',
        'elpis.status.no_date' => 'Besteld, datum onbekend',
        'elpis.status.to_order' => 'Te bestellen',
        'elpis.status.partial' => 'Deels gepicked',
        'elpis.status.partial_stock' => 'Deels gepicked, rest op voorraad',
        'elpis.status.partial_ordered' => 'Deels gepicked, rest besteld',
        'elpis.status.delivery_late' => 'Levering te laat',
        'elpis.status.order_too_late' => 'Te laat besteld',
        'elpis.receipt.planned' => 'gepland %s',
        'elpis.receipt.order_too_late' => 'Levertijd %s dagen haalt planningsdatum niet',
        'elpis.status.partial_lc' => 'deels gepicked',
        'elpis.status.ordered' => 'Besteld',
        'elpis.status.stock' => 'Op voorraad',
        'elpis.status_info.return' => 'Niets nodig (hoeveelheid 0) of retourregel.',
        'elpis.status_info.closed' => 'De werkorder is afgesloten.',
        'elpis.status_info.picked' => 'Alles is gepicked of verbruikt.',
        'elpis.status_info.late' => 'De planningsdatum is voorbij en het materiaal is nog niet binnen of gepicked.',
        'elpis.status_info.delivery_late' => 'De verwachte ontvangst valt na de planningsdatum of is al verstreken.',
        'elpis.status_info.order_too_late' => 'Nog niets besteld en met de levertijd (of uiterste besteldatum) is de planningsdatum niet meer haalbaar.',
        'elpis.status_info.no_date' => 'Besteld, maar de inkoop heeft geen verwachte ontvangstdatum.',
        'elpis.status_info.to_order' => 'Er is nog een tekort dat niet gedekt is door ontvangst, reservering, inkoop of vrije voorraad.',
        'elpis.status_info.inbound' => 'Ontvangen en ligt nog in bin INBOUND; het magazijn moet het klaarleggen.',
        'elpis.status_info.partial_received' => 'Deels gepicked; de rest is ontvangen of gereserveerd voor dit project en moet nog gepicked worden.',
        'elpis.status_info.partial_stock' => 'Deels gepicked; de rest komt uit vrije voorraad.',
        'elpis.status_info.partial_ordered' => 'Deels gepicked; de rest is op tijd besteld.',
        'elpis.status_info.ordered' => 'Gedekt door open inkoop die op tijd binnenkomt.',
        'elpis.status_info.received_to_pick' => 'Ontvangen of gereserveerd voor dit project (niet uit vrije voorraad), maar nog niet gepicked.',
        'elpis.status_info.stock' => 'Gedekt door vrije voorraad (kolom Voorraad), nog niet gepicked.',
        'elpis.btn.status_info' => 'Info',
        'elpis.status_info.title' => 'Uitleg statussen',
        'elpis.status_info.tones' => 'Groen = in orde, oranje = aandacht nodig, rood = echt probleem, grijs = buiten beschouwing. Oranje en rood tellen als probleemregel.',
        'elpis.status.all' => 'Alle statussen',
        'elpis.reason.numbers' => 'Nodig %s, gepicked %s, %s ontvangen/gereserveerd voor dit project, vrije voorraad %s, open inkoop %s, tekort %s',
        'elpis.reason.return' => 'Nodig %s: retour of niet nodig',
        'elpis.reason.closed' => 'Werkorder is afgesloten',
        'elpis.reason.picked' => 'Alles is gepicked of verbruikt',
        'elpis.reason.late' => 'Planningsdatum %s is voorbij en het materiaal is nog niet binnen',
        'elpis.reason.delivery_late' => 'Verwachte ontvangst %s valt na planningsdatum %s of is al verstreken',
        'elpis.reason.order_too_late' => 'Niets besteld; levertijd %s valt na planningsdatum %s',
        'elpis.reason.order_too_late_date' => 'Niets besteld; uiterste besteldatum %s is voorbij',
        'elpis.reason.no_date' => 'Besteld, maar zonder verwachte ontvangstdatum',
        'elpis.reason.to_order' => 'Nog %s te bestellen; niet gedekt door ontvangst, reservering, inkoop of vrije voorraad',
        'elpis.reason.inbound' => '%s ontvangen en ligt nog in bin INBOUND',
        'elpis.reason.received_to_pick' => 'Gedekt door ontvangst/reservering voor dit project; nog niet gepicked',
        'elpis.reason.ordered' => 'Gedekt door open inkoop, verwacht %s',
        'elpis.reason.stock' => 'Gedekt door vrije voorraad; nog niet gepicked',
        'elpis.status.received_to_pick' => 'Ontvangen, nog picken',
        'elpis.status.partial_received' => 'Deels gepicked, rest ontvangen',
        'elpis.status.inbound' => 'In behandeling magazijn',
        'elpis.receipt.inbound' => 'Ontvangen, ligt in INBOUND (%s) – magazijn legt klaar',
        'elpis.receipt.fully_picked' => 'Volledig gepicked',
        'elpis.receipt.expected' => 'Verwacht %s',
        'elpis.summary.to_order' => 'nog te bestellen',
        'elpis.mail.reason.to_order' => '%s nog te bestellen',
        'elpis.label.problems_only' => 'Alleen probleemregels tonen',
        'elpis.label.projects_problems_only' => 'Alleen projecten met probleemregels',
        'elpis.btn.export_xlsx' => 'Exporteer .xlsx',
        'elpis.manager.invalid' => 'Onbekende projectmanager',
        'elpis.label.purchaser' => 'Besteller',
        'elpis.purchaser.all' => 'Iedereen',
        'elpis.purchaser.invalid' => 'Onbekende besteller',
        'elpis.col.purchaser' => 'Besteller',
        'elpis.col.needed' => 'Nodig',
        'elpis.loading.projects' => 'Bezig met laden…',
        'elpis.btn.refresh' => 'Ververs nu',
        'elpis.btn.close' => 'Sluiten',
        'elpis.btn.show_projects' => 'Toon projecten',
        'elpis.company.choose' => 'Kies een bedrijf',
        'elpis.company.hint' => 'Kies eerst een bedrijf. Daarna laden we de projecten.',
        'elpis.nightly.waiting' => 'De dagcache wordt bijgewerkt. De pagina herlaadt daarna vanzelf.',
        'elpis.nightly.failed' => 'De dagcache kon niet worden bijgewerkt. Probeer het later opnieuw.',
        'elpis.prefs.title' => 'E-mailvoorkeuren',
        'elpis.prefs.intro' => 'Kies wanneer Elpis een overzicht stuurt en welke artikelen daarin staan.',
        'elpis.prefs.frequency' => 'Wanneer',
        'elpis.prefs.daily' => 'Dagelijks (ma–vr)',
        'elpis.prefs.weekly' => 'Wekelijks (maandag)',
        'elpis.prefs.custom' => 'Aangepast',
        'elpis.prefs.never' => 'Nooit',
        'elpis.prefs.content' => 'Inhoud',
        'elpis.prefs.late_only' => 'Alleen te laat',
        'elpis.prefs.unconfirmed_only' => 'Alleen onbevestigd (geen verwachte datum)',
        'elpis.prefs.both' => 'Beide',
        'elpis.prefs.save' => 'Opslaan',
        'elpis.prefs.saved' => 'Voorkeuren opgeslagen.',
        'elpis.prefs.back' => 'Terug naar projecten',
        'elpis.weekday.1' => 'Maandag',
        'elpis.weekday.2' => 'Dinsdag',
        'elpis.weekday.3' => 'Woensdag',
        'elpis.weekday.4' => 'Donderdag',
        'elpis.weekday.5' => 'Vrijdag',
        'elpis.weekday.6' => 'Zaterdag',
        'elpis.weekday.7' => 'Zondag',
        'elpis.mail.subject' => 'Elpis: %d artikel(en) voor %s',
        'elpis.mail.intro' => 'Overzicht voor %s: %d artikel(en) die aandacht nodig hebben.',
        'elpis.mail.test_banner' => 'Testmodus. Deze mail zou naar %s gaan.',
        'elpis.mail.prefs_link' => 'E-mailvoorkeuren aanpassen',
        'elpis.mail.ticket_link' => 'Ticket maken',
        'elpis.mail.ticket_title' => '%s in %s vereist aandacht',
        'elpis.mail.ticket_what' => 'Wat moet er gebeuren:',
        'elpis.prefs.hour' => 'Tijdstip',
        'elpis.mail.col.project' => 'Project',
        'elpis.mail.col.receipt' => 'Ontvangst',
        'elpis.mail.col.note' => 'Toelichting',
        'elpis.mail.reason.missing_date' => 'Verwachte ontvangstdatum ontbreekt',
        'elpis.mail.late.day' => '%d dag te laat',
        'elpis.mail.late.days' => '%d dagen te laat',
        'elpis.mail.late.week' => '%d week te laat',
        'elpis.mail.late.weeks' => '%d weken te laat',
        'elpis.mail.late.month' => '%d maand te laat',
        'elpis.mail.late.months' => '%d maanden te laat',
    ],

    'en' => [
        'lang.menu_aria' => 'Choose language',
        'lang.switch_to' => 'Switch to %s',
        'app.title' => 'Receipt Monitor',
        'elpis.hero.title' => 'Receipt Monitor',
        'elpis.hero.subtitle' => 'View purchase planning lines per project and project manager.',
        'elpis.label.company' => 'Company',
        'elpis.label.manager' => 'Project manager',
        'elpis.manager.all' => 'Everyone',
        'elpis.section.projects' => 'Projects',
        'elpis.label.search' => 'Search',
        'elpis.placeholder.search' => 'Project no. or description',
        'elpis.label.line_search' => 'Search lines',
        'elpis.placeholder.line_search' => 'Work order, item or description',
        'elpis.empty.search' => 'No projects found for this search.',
        'elpis.meta.manager' => 'Project manager',
        'elpis.col.workorder' => 'Work order',
        'elpis.col.item' => 'Item',
        'elpis.col.description' => 'Description',
        'elpis.col.to_order' => 'To order',
        'elpis.col.ordered' => 'Ordered',
        'elpis.col.outstanding' => 'Outstanding',
        'elpis.col.received' => 'Received',
        'elpis.col.picked' => 'Picked',
        'elpis.tip.picked' => '%s picked, %s needed.',
        'elpis.col.line_status' => 'Status',
        'elpis.col.expected_receipt' => 'Expected receipt',
        'elpis.receipt.on_stock' => 'In stock',
        'elpis.receipt.missing' => 'Date missing',
        'elpis.receipt.stock' => 'From stock',
        'elpis.receipt.partial' => 'Partly in stock, expected %s',
        'elpis.receipt.partial_missing' => 'Partly in stock, date missing',
        'elpis.receipt.uncovered' => 'Expected %s, remainder not covered',
        'elpis.receipt.partial_uncovered' => 'Partly in stock, expected %s, remainder not covered',
        'elpis.summary.execution_from' => 'Execution can be planned from',
        'elpis.summary.direct' => 'now',
        'elpis.summary.missing' => 'date missing',
        'elpis.empty.managers' => 'No project managers found',
        'elpis.empty.projects' => 'No projects found for this project manager.',
        'elpis.empty.lines' => 'No item lines for this project.',
        'elpis.error.load_failed' => 'Failed to load data. Please try again later.',
        'elpis.loader.wait' => 'Please wait...',
        'elpis.loader.loading' => 'Fetching data from Business Central',
        'elpis.btn.expand_all' => 'Expand all',
        'elpis.btn.collapse_all' => 'Collapse all',
        'elpis.label.status_filter' => 'Status',
        'elpis.link.copy' => 'Copy link to this project',
        'elpis.link.copied' => 'Link copied',
        'elpis.link.copy_failed' => 'Copy failed',
        'elpis.col.planning_date' => 'Planning date',
        'elpis.col.stock' => 'Stock',
        'elpis.receipt.already' => 'Already received (%s)',
        'elpis.receipt.already_nodate' => 'Already received',
        'elpis.receipt.to_order_suffix' => '%s still to order',
        'elpis.receipt.late' => 'Late',
        'elpis.status.return' => 'Return / n/a',
        'elpis.status.closed' => 'Closed',
        'elpis.status.picked' => 'Picked',
        'elpis.status.late' => 'Late',
        'elpis.status.no_date' => 'Ordered, date unknown',
        'elpis.status.to_order' => 'To order',
        'elpis.status.partial' => 'Partly picked',
        'elpis.status.partial_stock' => 'Partly picked, rest in stock',
        'elpis.status.partial_ordered' => 'Partly picked, rest ordered',
        'elpis.status.delivery_late' => 'Delivery too late',
        'elpis.status.order_too_late' => 'Ordered too late',
        'elpis.receipt.planned' => 'planned %s',
        'elpis.receipt.order_too_late' => 'Lead time of %s days misses the planning date',
        'elpis.status.partial_lc' => 'partly picked',
        'elpis.status.ordered' => 'Ordered',
        'elpis.status.stock' => 'In stock',
        'elpis.status_info.return' => 'Nothing needed (quantity 0) or a return line.',
        'elpis.status_info.closed' => 'The work order is closed.',
        'elpis.status_info.picked' => 'Everything is picked or consumed.',
        'elpis.status_info.late' => 'The planning date has passed and the material is not in or picked yet.',
        'elpis.status_info.delivery_late' => 'The expected receipt is after the planning date or has already passed.',
        'elpis.status_info.order_too_late' => 'Nothing ordered yet and the lead time (or latest order date) no longer meets the planning date.',
        'elpis.status_info.no_date' => 'Ordered, but the purchase has no expected receipt date.',
        'elpis.status_info.to_order' => 'There is a shortage not covered by receipt, reservation, purchase or free stock.',
        'elpis.status_info.inbound' => 'Received and still in bin INBOUND; the warehouse still has to prepare it.',
        'elpis.status_info.partial_received' => 'Partly picked; the rest is received or reserved for this project and still has to be picked.',
        'elpis.status_info.partial_stock' => 'Partly picked; the rest comes from free stock.',
        'elpis.status_info.partial_ordered' => 'Partly picked; the rest is ordered on time.',
        'elpis.status_info.ordered' => 'Covered by open purchase arriving on time.',
        'elpis.status_info.received_to_pick' => 'Received or reserved for this project (not free stock), but not picked yet.',
        'elpis.status_info.stock' => 'Covered by free stock (Stock column), not picked yet.',
        'elpis.btn.status_info' => 'Info',
        'elpis.status_info.title' => 'Status explanation',
        'elpis.status_info.tones' => 'Green = OK, orange = needs attention, red = real problem, grey = out of scope. Orange and red count as problem lines.',
        'elpis.status.all' => 'All statuses',
        'elpis.reason.numbers' => 'Needed %s, picked %s, %s received/reserved for this project, free stock %s, open purchase %s, shortage %s',
        'elpis.reason.return' => 'Needed %s: return or not required',
        'elpis.reason.closed' => 'Work order is closed',
        'elpis.reason.picked' => 'Everything is picked or consumed',
        'elpis.reason.late' => 'Planning date %s has passed and the material is not in yet',
        'elpis.reason.delivery_late' => 'Expected receipt %s is after planning date %s or has already passed',
        'elpis.reason.order_too_late' => 'Nothing ordered; lead time %s ends after planning date %s',
        'elpis.reason.order_too_late_date' => 'Nothing ordered; latest order date %s has passed',
        'elpis.reason.no_date' => 'Ordered, but without an expected receipt date',
        'elpis.reason.to_order' => '%s still to order; not covered by receipt, reservation, purchase or free stock',
        'elpis.reason.inbound' => '%s received and still in bin INBOUND',
        'elpis.reason.received_to_pick' => 'Covered by receipt/reservation for this project; not picked yet',
        'elpis.reason.ordered' => 'Covered by open purchase, expected %s',
        'elpis.reason.stock' => 'Covered by free stock; not picked yet',
        'elpis.status.received_to_pick' => 'Received, still to pick',
        'elpis.status.partial_received' => 'Partly picked, rest received',
        'elpis.status.inbound' => 'Being handled by warehouse',
        'elpis.receipt.inbound' => 'Received, in INBOUND (%s) – warehouse to prepare',
        'elpis.receipt.fully_picked' => 'Fully picked',
        'elpis.receipt.expected' => 'Expected %s',
        'elpis.summary.to_order' => 'still to order',
        'elpis.mail.reason.to_order' => '%s still to order',
        'elpis.label.problems_only' => 'Show problem lines only',
        'elpis.label.projects_problems_only' => 'Only projects with problem lines',
        'elpis.btn.export_xlsx' => 'Export .xlsx',
        'elpis.manager.invalid' => 'Unknown project manager',
        'elpis.label.purchaser' => 'Purchaser',
        'elpis.purchaser.all' => 'Everyone',
        'elpis.purchaser.invalid' => 'Unknown purchaser',
        'elpis.col.purchaser' => 'Purchaser',
        'elpis.col.needed' => 'Needed',
        'elpis.loading.projects' => 'Loading…',
        'elpis.btn.refresh' => 'Refresh now',
        'elpis.btn.close' => 'Close',
        'elpis.btn.show_projects' => 'Show projects',
        'elpis.company.choose' => 'Choose a company',
        'elpis.company.hint' => 'Choose a company first. Projects load after that.',
        'elpis.nightly.waiting' => 'The daily cache is being refreshed. The page will reload.',
        'elpis.nightly.failed' => 'The daily cache could not be refreshed. Try again later.',
        'elpis.prefs.title' => 'Email preferences',
        'elpis.prefs.intro' => 'Choose when Elpis sends an overview and which items it includes.',
        'elpis.prefs.frequency' => 'When',
        'elpis.prefs.daily' => 'Daily (Mon–Fri)',
        'elpis.prefs.weekly' => 'Weekly (Monday)',
        'elpis.prefs.custom' => 'Custom',
        'elpis.prefs.never' => 'Never',
        'elpis.prefs.content' => 'Content',
        'elpis.prefs.late_only' => 'Late only',
        'elpis.prefs.unconfirmed_only' => 'Unconfirmed only (no expected date)',
        'elpis.prefs.both' => 'Both',
        'elpis.prefs.save' => 'Save',
        'elpis.prefs.saved' => 'Preferences saved.',
        'elpis.prefs.back' => 'Back to projects',
        'elpis.weekday.1' => 'Monday',
        'elpis.weekday.2' => 'Tuesday',
        'elpis.weekday.3' => 'Wednesday',
        'elpis.weekday.4' => 'Thursday',
        'elpis.weekday.5' => 'Friday',
        'elpis.weekday.6' => 'Saturday',
        'elpis.weekday.7' => 'Sunday',
        'elpis.mail.subject' => 'Elpis: %d item(s) for %s',
        'elpis.mail.intro' => 'Overview for %s: %d item(s) that need attention.',
        'elpis.mail.test_banner' => 'Test mode. This message would go to %s.',
        'elpis.mail.prefs_link' => 'Change email preferences',
        'elpis.mail.ticket_link' => 'Create ticket',
        'elpis.mail.ticket_title' => '%s in %s needs attention',
        'elpis.mail.ticket_what' => 'What needs to happen:',
        'elpis.prefs.hour' => 'Time',
        'elpis.mail.col.project' => 'Project',
        'elpis.mail.col.receipt' => 'Receipt',
        'elpis.mail.col.note' => 'Explanation',
        'elpis.mail.reason.missing_date' => 'Expected receipt date is missing',
        'elpis.mail.late.day' => '%d day late',
        'elpis.mail.late.days' => '%d days late',
        'elpis.mail.late.week' => '%d week late',
        'elpis.mail.late.weeks' => '%d weeks late',
        'elpis.mail.late.month' => '%d month late',
        'elpis.mail.late.months' => '%d months late',
    ],

    'de' => [
        'lang.menu_aria' => 'Sprache wählen',
        'lang.switch_to' => 'Wechseln zu %s',
        'app.title' => 'Wareneingangsmonitor',
        'elpis.hero.title' => 'Wareneingangsmonitor',
        'elpis.hero.subtitle' => 'Einkaufsplanungszeilen pro Projekt und Projektmanager anzeigen.',
        'elpis.label.company' => 'Unternehmen',
        'elpis.label.manager' => 'Projektmanager',
        'elpis.manager.all' => 'Alle',
        'elpis.section.projects' => 'Projekte',
        'elpis.label.search' => 'Suchen',
        'elpis.placeholder.search' => 'Projektnr., Arbeitsauftrag, Artikel oder Beschreibung',
        'elpis.label.line_search' => 'Zeilen suchen',
        'elpis.placeholder.line_search' => 'Arbeitsauftrag, Artikel oder Beschreibung',
        'elpis.empty.search' => 'Keine Projekte für diese Suche gefunden.',
        'elpis.meta.manager' => 'Projektmanager',
        'elpis.col.workorder' => 'Arbeitsauftrag',
        'elpis.col.item' => 'Artikel',
        'elpis.col.description' => 'Beschreibung',
        'elpis.col.to_order' => 'Zu bestellen',
        'elpis.col.ordered' => 'Bestellt',
        'elpis.col.outstanding' => 'Offen',
        'elpis.col.received' => 'Erhalten',
        'elpis.col.picked' => 'Kommissioniert',
        'elpis.tip.picked' => '%s kommissioniert, %s benötigt.',
        'elpis.col.line_status' => 'Status',
        'elpis.col.expected_receipt' => 'Erwarteter Eingang',
        'elpis.receipt.on_stock' => 'Auf Lager',
        'elpis.receipt.missing' => 'Datum fehlt',
        'elpis.receipt.stock' => 'Aus Lager',
        'elpis.receipt.partial' => 'Teilweise auf Lager, erwartet %s',
        'elpis.receipt.partial_missing' => 'Teilweise auf Lager, Datum fehlt',
        'elpis.receipt.uncovered' => 'Erwartet %s, Rest nicht gedeckt',
        'elpis.receipt.partial_uncovered' => 'Teilweise auf Lager, erwartet %s, Rest nicht gedeckt',
        'elpis.summary.execution_from' => 'Ausführung planbar ab',
        'elpis.summary.direct' => 'sofort',
        'elpis.summary.missing' => 'Datum fehlt',
        'elpis.empty.managers' => 'Keine Projektmanager gefunden',
        'elpis.empty.projects' => 'Keine Projekte für diesen Projektmanager gefunden.',
        'elpis.empty.lines' => 'Keine Artikelzeilen für dieses Projekt.',
        'elpis.error.load_failed' => 'Daten konnten nicht geladen werden. Bitte später erneut versuchen.',
        'elpis.loader.wait' => 'Bitte warten...',
        'elpis.loader.loading' => 'Daten werden aus Business Central geladen',
        'elpis.btn.expand_all' => 'Alle aufklappen',
        'elpis.btn.collapse_all' => 'Alle zuklappen',
        'elpis.label.status_filter' => 'Status',
        'elpis.link.copy' => 'Link zu diesem Projekt kopieren',
        'elpis.link.copied' => 'Link kopiert',
        'elpis.link.copy_failed' => 'Kopieren fehlgeschlagen',
        'elpis.col.planning_date' => 'Planungsdatum',
        'elpis.col.stock' => 'Bestand',
        'elpis.receipt.already' => 'Bereits erhalten (%s)',
        'elpis.receipt.already_nodate' => 'Bereits erhalten',
        'elpis.receipt.to_order_suffix' => '%s noch zu bestellen',
        'elpis.receipt.late' => 'Zu spät',
        'elpis.btn.refresh' => 'Jetzt aktualisieren',
        'elpis.btn.close' => 'Schließen',
        'elpis.btn.show_projects' => 'Projekte anzeigen',
        'elpis.company.choose' => 'Unternehmen wählen',
        'elpis.company.hint' => 'Wählen Sie zuerst ein Unternehmen. Danach laden wir die Projekte.',
        'elpis.nightly.waiting' => 'Der Tagescache wird aktualisiert. Die Seite lädt danach neu.',
        'elpis.nightly.failed' => 'Der Tagescache konnte nicht aktualisiert werden.',
        'elpis.prefs.title' => 'E-Mail-Einstellungen',
        'elpis.prefs.intro' => 'Legen Sie fest, wann Elpis eine Übersicht sendet und welche Artikel darin stehen.',
        'elpis.prefs.frequency' => 'Wann',
        'elpis.prefs.daily' => 'Täglich (Mo–Fr)',
        'elpis.prefs.weekly' => 'Wöchentlich (Montag)',
        'elpis.prefs.custom' => 'Benutzerdefiniert',
        'elpis.prefs.never' => 'Nie',
        'elpis.prefs.content' => 'Inhalt',
        'elpis.prefs.late_only' => 'Nur verspätet',
        'elpis.prefs.unconfirmed_only' => 'Nur unbestätigt (kein erwartetes Datum)',
        'elpis.prefs.both' => 'Beides',
        'elpis.prefs.save' => 'Speichern',
        'elpis.prefs.saved' => 'Einstellungen gespeichert.',
        'elpis.prefs.back' => 'Zurück zu den Projekten',
        'elpis.weekday.1' => 'Montag',
        'elpis.weekday.2' => 'Dienstag',
        'elpis.weekday.3' => 'Mittwoch',
        'elpis.weekday.4' => 'Donnerstag',
        'elpis.weekday.5' => 'Freitag',
        'elpis.weekday.6' => 'Samstag',
        'elpis.weekday.7' => 'Sonntag',
        'elpis.mail.subject' => 'Elpis: %d Artikel für %s',
        'elpis.mail.intro' => 'Übersicht für %s: %d Artikel, die Aufmerksamkeit brauchen.',
        'elpis.mail.test_banner' => 'Testmodus. Diese Mail würde an %s gehen.',
        'elpis.mail.prefs_link' => 'E-Mail-Einstellungen ändern',
        'elpis.mail.ticket_link' => 'Ticket erstellen',
        'elpis.mail.ticket_title' => '%s in %s erfordert Aufmerksamkeit',
        'elpis.mail.ticket_what' => 'Was muss passieren:',
        'elpis.prefs.hour' => 'Uhrzeit',
        'elpis.mail.col.project' => 'Projekt',
        'elpis.mail.col.receipt' => 'Eingang',
        'elpis.mail.col.note' => 'Erläuterung',
        'elpis.mail.reason.missing_date' => 'Erwartetes Eingangsdatum fehlt',
        'elpis.mail.late.day' => '%d Tag zu spät',
        'elpis.mail.late.days' => '%d Tage zu spät',
        'elpis.mail.late.week' => '%d Woche zu spät',
        'elpis.mail.late.weeks' => '%d Wochen zu spät',
        'elpis.mail.late.month' => '%d Monat zu spät',
        'elpis.mail.late.months' => '%d Monate zu spät',
    ],

    'fr' => [
        'lang.menu_aria' => 'Choisir la langue',
        'lang.switch_to' => 'Passer en %s',
        'app.title' => 'Moniteur de réception',
        'elpis.hero.title' => 'Moniteur de réception',
        'elpis.hero.subtitle' => 'Consultez les lignes de planification d\'achat par projet et chef de projet.',
        'elpis.label.company' => 'Société',
        'elpis.label.manager' => 'Chef de projet',
        'elpis.manager.all' => 'Tous',
        'elpis.section.projects' => 'Projets',
        'elpis.label.search' => 'Rechercher',
        'elpis.placeholder.search' => 'N° projet, ordre de travail, article ou description',
        'elpis.label.line_search' => 'Rechercher dans les lignes',
        'elpis.placeholder.line_search' => 'Ordre de travail, article ou description',
        'elpis.empty.search' => 'Aucun projet trouvé pour cette recherche.',
        'elpis.meta.manager' => 'Chef de projet',
        'elpis.col.workorder' => 'Ordre de travail',
        'elpis.col.item' => 'Article',
        'elpis.col.description' => 'Description',
        'elpis.col.to_order' => 'À commander',
        'elpis.col.ordered' => 'Commandé',
        'elpis.col.outstanding' => 'En cours',
        'elpis.col.received' => 'Reçu',
        'elpis.col.picked' => 'Prélevé',
        'elpis.tip.picked' => '%s prélevé(s), %s nécessaire(s).',
        'elpis.col.line_status' => 'Statut',
        'elpis.col.expected_receipt' => 'Réception prévue',
        'elpis.receipt.on_stock' => 'En stock',
        'elpis.receipt.missing' => 'Date manquante',
        'elpis.receipt.stock' => 'Du stock',
        'elpis.receipt.partial' => 'Partiellement en stock, prévu le %s',
        'elpis.receipt.partial_missing' => 'Partiellement en stock, date manquante',
        'elpis.receipt.uncovered' => 'Prévu le %s, reste non couvert',
        'elpis.receipt.partial_uncovered' => 'Partiellement en stock, prévu le %s, reste non couvert',
        'elpis.summary.execution_from' => 'Exécution à planifier à partir du',
        'elpis.summary.direct' => 'maintenant',
        'elpis.summary.missing' => 'date manquante',
        'elpis.empty.managers' => 'Aucun chef de projet trouvé',
        'elpis.empty.projects' => 'Aucun projet trouvé pour ce chef de projet.',
        'elpis.empty.lines' => 'Aucune ligne article pour ce projet.',
        'elpis.error.load_failed' => 'Échec du chargement des données. Réessayez plus tard.',
        'elpis.loader.wait' => 'Veuillez patienter...',
        'elpis.loader.loading' => 'Récupération des données depuis Business Central',
        'elpis.btn.expand_all' => 'Tout déplier',
        'elpis.btn.collapse_all' => 'Tout replier',
        'elpis.label.status_filter' => 'Statut',
        'elpis.link.copy' => 'Copier le lien vers ce projet',
        'elpis.link.copied' => 'Lien copié',
        'elpis.link.copy_failed' => 'Échec de la copie',
        'elpis.col.planning_date' => 'Date de planification',
        'elpis.col.stock' => 'Stock',
        'elpis.receipt.already' => 'Déjà reçu (%s)',
        'elpis.receipt.already_nodate' => 'Déjà reçu',
        'elpis.receipt.to_order_suffix' => '%s encore à commander',
        'elpis.receipt.late' => 'En retard',
        'elpis.btn.refresh' => 'Actualiser',
        'elpis.btn.close' => 'Fermer',
        'elpis.btn.show_projects' => 'Afficher les projets',
        'elpis.company.choose' => 'Choisir une société',
        'elpis.company.hint' => 'Choisissez d\'abord une société. Les projets se chargent ensuite.',
        'elpis.nightly.waiting' => 'Le cache du jour est en cours de mise à jour. La page se recharge ensuite.',
        'elpis.nightly.failed' => 'Le cache du jour n\'a pas pu être mis à jour.',
        'elpis.prefs.title' => 'Préférences e-mail',
        'elpis.prefs.intro' => 'Choisissez quand Elpis envoie un aperçu et quels articles il contient.',
        'elpis.prefs.frequency' => 'Quand',
        'elpis.prefs.daily' => 'Quotidien (lun–ven)',
        'elpis.prefs.weekly' => 'Hebdomadaire (lundi)',
        'elpis.prefs.custom' => 'Personnalisé',
        'elpis.prefs.never' => 'Jamais',
        'elpis.prefs.content' => 'Contenu',
        'elpis.prefs.late_only' => 'Uniquement en retard',
        'elpis.prefs.unconfirmed_only' => 'Uniquement non confirmé (pas de date prévue)',
        'elpis.prefs.both' => 'Les deux',
        'elpis.prefs.save' => 'Enregistrer',
        'elpis.prefs.saved' => 'Préférences enregistrées.',
        'elpis.prefs.back' => 'Retour aux projets',
        'elpis.weekday.1' => 'Lundi',
        'elpis.weekday.2' => 'Mardi',
        'elpis.weekday.3' => 'Mercredi',
        'elpis.weekday.4' => 'Jeudi',
        'elpis.weekday.5' => 'Vendredi',
        'elpis.weekday.6' => 'Samedi',
        'elpis.weekday.7' => 'Dimanche',
        'elpis.mail.subject' => 'Elpis : %d article(s) pour %s',
        'elpis.mail.intro' => 'Aperçu pour %s : %d article(s) à suivre.',
        'elpis.mail.test_banner' => 'Mode test. Ce message irait à %s.',
        'elpis.mail.prefs_link' => 'Modifier les préférences e-mail',
        'elpis.mail.ticket_link' => 'Créer un ticket',
        'elpis.mail.ticket_title' => '%s dans %s nécessite une attention',
        'elpis.mail.ticket_what' => 'Que faut-il faire :',
        'elpis.prefs.hour' => 'Heure',
        'elpis.mail.col.project' => 'Projet',
        'elpis.mail.col.receipt' => 'Réception',
        'elpis.mail.col.note' => 'Explication',
        'elpis.mail.reason.missing_date' => 'Date de réception prévue manquante',
        'elpis.mail.late.day' => '%d jour de retard',
        'elpis.mail.late.days' => '%d jours de retard',
        'elpis.mail.late.week' => '%d semaine de retard',
        'elpis.mail.late.weeks' => '%d semaines de retard',
        'elpis.mail.late.month' => '%d mois de retard',
        'elpis.mail.late.months' => '%d mois de retard',
    ],
];

/**
 * Functies
 */

function getUserPrefsPath(string $email): ?string
{
    $email = strtolower(trim($email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }
    $root = function_exists('elpis_data_dir') ? elpis_data_dir() : (__DIR__ . '/data');
    $dir = $root . '/user_prefs';
    $filename = preg_replace('/[^a-z0-9._\-]/', '_', $email) . '.json';
    return $dir . '/' . $filename;
}

function loadUserPrefs(string $email): array
{
    $path = getUserPrefsPath($email);
    if ($path === null || !is_file($path)) {
        return [];
    }
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function saveUserPref(string $email, string $key, mixed $value): void
{
    $path = getUserPrefsPath($email);
    if ($path === null) {
        return;
    }
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    $prefs = loadUserPrefs($email);
    $prefs[$key] = $value;
    file_put_contents($path, json_encode($prefs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function getCurrentLanguage(): string
{
    $lang = (string) ($_SESSION['lang'] ?? 'nl');
    return array_key_exists($lang, SUPPORTED_LANGUAGES) ? $lang : 'nl';
}

function getHtmlLang(): string
{
    return getCurrentLanguage();
}

function getDateLocale(): string
{
    $lang = getCurrentLanguage();
    return LOCALE_BY_LANG[$lang] ?? 'nl-NL';
}

/**
 * Geeft de vertaling voor $key in de actieve taal.
 * Extra $args worden via sprintf ingevoegd (voor %d, %s, etc.).
 */
function LOC(string $key, mixed ...$args): string
{
    $lang = getCurrentLanguage();
    $translations = TRANSLATIONS[$lang] ?? TRANSLATIONS['nl'];
    $string = $translations[$key] ?? (TRANSLATIONS['nl'][$key] ?? $key);

    return $args !== [] ? sprintf($string, ...$args) : $string;
}

function localizationFlagSvg(string $lang): string
{
    $svg = FLAG_SVGS[$lang] ?? '';
    if ($svg === '') {
        return '';
    }

    $safeLang = preg_replace('/[^a-z0-9]/', '', $lang) ?? $lang;
    return str_replace(
        ['id="a"', 'url(#a)', 'id="b"', 'url(#b)'],
        ['id="flag-' . $safeLang . '-a"', 'url(#flag-' . $safeLang . '-a)', 'id="flag-' . $safeLang . '-b"', 'url(#flag-' . $safeLang . '-b)'],
        $svg
    );
}

function localizationUrlWithLang(string $lang): string
{
    $params = $_GET;
    unset($params['lang']);
    $params['lang'] = $lang;
    $path = strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '?') ?: '';
    $query = http_build_query($params);
    return $path . ($query !== '' ? '?' . $query : '');
}

function localizationJsTranslations(array $keys): string
{
    $payload = [];
    foreach ($keys as $key) {
        $payload[$key] = LOC($key);
    }

    return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function renderLanguageSwitcherStyles(): void
{
    echo <<<'CSS'
<style>
.lang-switcher {
    position: fixed;
    top: 12px;
    right: 12px;
    z-index: 5000;
    font-family: inherit;
}
.lang-switcher-toggle {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 30px;
    padding: 0;
    border: 1px solid rgba(0, 82, 155, 0.25);
    border-radius: 6px;
    background: #ffffff;
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.12);
    cursor: pointer;
}
.lang-switcher-toggle:hover {
    background: #f2f9ff;
}
.lang-switcher-toggle svg {
    width: 28px;
    height: auto;
    display: block;
    border-radius: 2px;
    overflow: hidden;
}
.lang-switcher-menu {
    position: absolute;
    top: calc(100% + 6px);
    right: 0;
    min-width: 160px;
    margin: 0;
    padding: 6px;
    list-style: none;
    background: #ffffff;
    border: 1px solid #c9d7eb;
    border-radius: 10px;
    box-shadow: 0 12px 28px rgba(15, 23, 42, 0.18);
    display: none;
}
.lang-switcher.is-open .lang-switcher-menu {
    display: block;
}
.lang-switcher-item a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    border-radius: 8px;
    color: var(--kvt-text, #1f2937);
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
}
.lang-switcher-item a:hover {
    background: #edf7ff;
}
.lang-switcher-item.is-active a {
    background: #e6f4ff;
}
.lang-switcher-item svg {
    width: 24px;
    height: auto;
    flex-shrink: 0;
    border-radius: 2px;
    overflow: hidden;
}
@media print {
    .lang-switcher {
        display: none !important;
    }
}
</style>
CSS;
}

function renderLanguageSwitcher(): void
{
    $current = getCurrentLanguage();
    $menuAria = htmlspecialchars(LOC('lang.menu_aria'), ENT_QUOTES);

    echo '<div class="lang-switcher" data-lang-switcher>';
    echo '<button type="button" class="lang-switcher-toggle" aria-haspopup="true" aria-expanded="false" aria-label="' . $menuAria . '">';
    echo localizationFlagSvg($current);
    echo '</button>';
    echo '<ul class="lang-switcher-menu" role="menu">';

    foreach (SUPPORTED_LANGUAGES as $code => $meta) {
        if ($code === $current) {
            continue;
        }

        $label = (string) ($meta['label'] ?? $code);
        $href = htmlspecialchars(localizationUrlWithLang($code), ENT_QUOTES);
        $title = htmlspecialchars(LOC('lang.switch_to', $label), ENT_QUOTES);

        echo '<li class="lang-switcher-item" role="none">';
        echo '<a role="menuitem" href="' . $href . '" title="' . $title . '">';
        echo localizationFlagSvg($code);
        echo '<span>' . htmlspecialchars($label) . '</span>';
        echo '</a>';
        echo '</li>';
    }

    echo '</ul>';
    echo '</div>';
}

function renderLanguageSwitcherScript(): void
{
    echo <<<'JS'
<script>
(function () {
    document.querySelectorAll('[data-lang-switcher]').forEach(function (root) {
        var toggle = root.querySelector('.lang-switcher-toggle');
        if (!toggle) {
            return;
        }

        toggle.addEventListener('click', function (event) {
            event.stopPropagation();
            var isOpen = root.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        document.addEventListener('click', function () {
            root.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
        });

        root.addEventListener('click', function (event) {
            event.stopPropagation();
        });
    });
})();
</script>
JS;
}

/**
 * Page load
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

if (!isset($_SESSION['lang'])) {
    $prefEmail = strtolower(trim((string) ($_SESSION['user']['email'] ?? '')));
    if ($prefEmail !== '') {
        $savedPrefs = loadUserPrefs($prefEmail);
        if (isset($savedPrefs['lang']) && array_key_exists($savedPrefs['lang'], SUPPORTED_LANGUAGES)) {
            $_SESSION['lang'] = $savedPrefs['lang'];
        }
    }
}

if (!isset($_SESSION['lang']) || !array_key_exists((string) $_SESSION['lang'], SUPPORTED_LANGUAGES)) {
    $_SESSION['lang'] = 'nl';
}

if (isset($_GET['lang']) && array_key_exists($_GET['lang'], SUPPORTED_LANGUAGES)) {
    $requestedLang = (string) $_GET['lang'];
    $langChanged = $requestedLang !== getCurrentLanguage();
    $_SESSION['lang'] = $requestedLang;
    $prefEmail = strtolower(trim((string) ($_SESSION['user']['email'] ?? '')));
    if ($prefEmail !== '' && $langChanged) {
        saveUserPref($prefEmail, 'lang', $requestedLang);
    }

    $isApiAction = isset($_GET['action']) && trim((string) $_GET['action']) !== '';
    if (!$isApiAction && strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET') {
        $params = $_GET;
        unset($params['lang']);
        $path = strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '?') ?: '';
        $query = http_build_query($params);
        header('Location: ' . $path . ($query !== '' ? '?' . $query : ''));
        exit;
    }
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
