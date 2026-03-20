/**
 * Romanian Language Generators - C6 Level, Phase 4
 * Tema: vocabular_c6 (5 generators) + text_lectura_c6 (4 generators)
 */

// ============================================================================
// TEMA: VOCABULAR_C6 (5 generators)
// ============================================================================

/**
 * Generator 1: Polisemie și omonimie
 * Generates exercises for polysemy and homonymy
 */
export function generatePolisemieOmonimie() {
  const exemple = [
    { cuvant: 'bancă', sensuri: ['mobilă de ședere', 'instituție financiară'], tip: 'polisemie', explicatie: 'Același cuvânt cu sensuri conexe' },
    { cuvant: 'rând', sensuri: ['linie succesivă', 'tur, moment'], tip: 'polisemie', explicatie: 'Același cuvânt cu sensuri diferite' },
    { cuvant: 'ochi', sensuri: ['organ vizual', 'parte a acului'], tip: 'omonimie', explicatie: 'Cuvinte diferite cu aceeași formă' },
    { cuvant: 'pară', sensuri: ['fruct', 'stâlp de lemn'], tip: 'omonimie', explicatie: 'Cuvinte diferite cu aceeași pronunție' },
    { cuvant: 'curs', sensuri: ['lecie', 'mișcare a unui râu', 'pret'], tip: 'polisemie', explicatie: 'Cuvânt cu multiple semnificații conexe' },
  ];

  const random = exemple[Math.floor(Math.random() * exemple.length)];

  return {
    tema: 'vocabular_c6_polisemie_omonimie',
    cuvant: random.cuvant,
    tip_fenomen: random.tip,
    sensuri: random.sensuri,
    intrebare: `Explică diferența dintre polisemie și omonimie. Dă exemplu cu cuvântul "${random.cuvant}"`,
    explicatie_model: random.explicatie,
    sensuri_cuvant: random.sensuri,
    tip: 'analiza_polisemie_omonimie'
  };
}

/**
 * Generator 2: Derivare avansată
 * Generates exercises for advanced word derivation
 */
export function generateDerivareAvansat() {
  const exemple = [
    { cuvant_baza: 'frumos', derivate: ['frumusețe', 'frumos-aș', 'frumos-ă'], sufix: '-eț(e), -aș, -ă', tip: 'adjective → substantive/adverbe' },
    { cuvant_baza: 'citi', derivate: ['cititor', 'citire', 'recitire'], sufix: '-tor, -re, 're-', tip: 'verb → substantive/verb' },
    { cuvant_baza: 'cas', derivate: ['casă', 'castel', 'casierie'], sufix: '-ă, -tel, -erie', tip: 'substantiv → derivate' },
    { cuvant_baza: 'prietenie', derivate: ['prieten', 'prietenesc', 'neprietenous'], sufix: '-∅, -esc, 'ne-', tip: 'substantiv → adjective' },
    { cuvant_baza: 'lucru', derivate: ['lucrător', 'lucrare', 'reilucra'], sufix: '-tor, -are, 're-', tip: 'noun → agent nouns' },
  ];

  const random = exemple[Math.floor(Math.random() * exemple.length)];

  return {
    tema: 'vocabular_c6_derivare_avansat',
    cuvant_baza: random.cuvant_baza,
    derivate: random.derivate,
    sufix_utilizat: random.sufix,
    tip_derivare: random.tip,
    intrebare: `Derivează cuvântul "${random.cuvant_baza}" și explică sufixele folosite`,
    raspuns_derivate: random.derivate,
    explicatie_sufix: random.sufix,
    tip: 'derivare_morfologica'
  };
}

/**
 * Generator 3: Locuțiuni și expresii idiomatice
 * Generates exercises for phraseological units and idioms
 */
export function generateLocutiuni() {
  const exemple = [
    { locutiune: 'a da ochii peste cap', sens: 'a se îndrăgosti fără măsură', exemplu: 'A dat ochii peste cap pentru ea.' },
    { locutiune: 'a trage cortina', sens: 'a termina, a încheia', exemplu: 'Trebuie să tragem cortina la această poveste.' },
    { locutiune: 'a o duce bine', sens: 'a prospera, a sta bine', exemplu: 'Deși tânăr, o duce bine în afaceri.' },
    { locutiune: 'a-și ridica mânecile', sens: 'a se pregăti pentru efort', exemplu: 'Și-a ridicat mânecile și a început munca.' },
    { locutiune: 'a juca un rol', sens: 'a participa la ceva, a influența', exemplu: 'Prietenii au jucat un rol important în decizia mea.' },
  ];

  const random = exemple[Math.floor(Math.random() * exemple.length)];

  return {
    tema: 'vocabular_c6_locutiuni',
    locutiune: random.locutiune,
    sens_locutiune: random.sens,
    exemplu_contextual: random.exemplu,
    intrebare: `Explică sensul locuțiunii "${random.locutiune}" și dă un exemplu`,
    raspuns_sens: random.sens,
    raspuns_exemplu: random.exemplu,
    tip: 'explicare_idiomatica'
  };
}

/**
 * Generator 4: Neologisme
 * Generates exercises for neologisms (new words and meanings)
 */
export function generateNeologisme() {
  const exemple = [
    { neologism: 'selfie', origine: 'engleză self + -ie', sens: 'fotografie de sine', domeniu: 'tehnologie/social media' },
    { neologism: 'blogger', origine: 'engleză blog + -er', sens: 'persoană care scrie pe un blog', domeniu: 'internet' },
    { neologism: 'infodemic', origine: 'engleză information + pandemic', sens: 'răspândire de informații false', domeniu: 'media/sănătate' },
    { neologism: 'telecommuting', origine: 'engleză tele + commuting', sens: 'lucru de acasă prin internet', domeniu: 'muncă' },
    { neologism: 'crowdfunding', origine: 'engleză crowd + funding', sens: 'finanțare din mulțime', domeniu: 'economie' },
  ];

  const random = exemple[Math.floor(Math.random() * exemple.length)];

  return {
    tema: 'vocabular_c6_neologisme',
    neologism: random.neologism,
    origine_cuvant: random.origine,
    sens_neologism: random.sens,
    domeniu_utilizare: random.domeniu,
    intrebare: `Analizează neologismul "${random.neologism}": origine și sens`,
    raspuns_origine: random.origine,
    raspuns_sens: random.sens,
    tip: 'analiza_neologism'
  };
}

/**
 * Generator 5: Câmpuri semantice
 * Generates exercises for semantic fields
 */
export function generateCampuriSemantice() {
  const exemple = [
    { camp: 'familia', cuvinte: ['tată', 'mamă', 'frate', 'soră', 'bunic', 'bunică'], caracteristica: 'relații de rudenie' },
    { camp: 'culori', cuvinte: ['roșu', 'albastru', 'galben', 'verde', 'negru', 'alb'], caracteristica: 'nuanțe de lumină/pigment' },
    { camp: 'animale domestice', cuvinte: ['pisică', 'câine', 'cal', 'oaie', 'pasăre', 'pește'], caracteristica: 'animale domesticite' },
    { camp: 'simțuri', cuvinte: ['a vedea', 'a auzi', 'a mirosi', 'a gusta', 'a atinge'], caracteristica: 'percepții senzoriale' },
    { camp: 'emoții', cuvinte: ['bucurie', 'tristețe', 'ură', 'dragoste', 'frică', 'speranță'], caracteristica: 'stări emoționale' },
  ];

  const random = exemple[Math.floor(Math.random() * exemple.length)];

  return {
    tema: 'vocabular_c6_campuri_semantice',
    camp_semantic: random.camp,
    cuvinte_componente: random.cuvinte,
    caracteristica_camp: random.caracteristica,
    intrebare: `Identifică câmpul semantic și explică relația între cuvintele: ${random.cuvinte.join(', ')}`,
    raspuns_camp: random.camp,
    explicatie_relatie: random.caracteristica,
    tip: 'definire_camp_semantic'
  };
}

// ============================================================================
// TEMA: TEXT_LECTURA_C6 (4 generators)
// ============================================================================

/**
 * Generator 6: Genuri și specii literare
 * Generates exercises for literary genres and species
 */
export function generateGenuriLiterare() {
  const exemple = [
    { gen: 'epică', specii: ['roman', 'nuvelă', 'epopeea'], caracteristici: 'narative cu acțiune în trecut' },
    { gen: 'lirică', specii: ['poezia', 'odă', 'elegie'], caracteristici: 'expresie a sentimentelor și emoțiilor' },
    { gen: 'dramatică', specii: ['tragedie', 'comedie', 'dramă'], caracteristici: 'reprezentare pe scenă cu dialog' },
    { gen: 'science-fiction', specii: ['distopie', 'utopie', 'cyberpunk'], caracteristici: 'acțiune în viitor cu tehnologie fantastică' },
    { gen: 'groază', specii: ['horror', 'mister', 'thriller'], caracteristici: 'creare de suspans și frică' },
  ];

  const random = exemple[Math.floor(Math.random() * exemple.length)];

  return {
    tema: 'text_lectura_c6_genuri_literare',
    gen_literar: random.gen,
    specii_gen: random.specii,
    caracteristici: random.caracteristici,
    intrebare: `Clasifică textele literare în genuri și specii. Exemplu: "${random.gen}" include: ${random.specii.join(', ')}`,
    raspuns_specii: random.specii,
    explicatie_caracteristici: random.caracteristici,
    tip: 'clasificare_gen_literar'
  };
}

/**
 * Generator 7: Moduri de expunere
 * Generates exercises for modes of discourse
 */
export function generateModuriExpunere() {
  const exemple = [
    { mod: 'narațiune', descriere: 'Relatan unei secvențe de evenimente în ordine cronologică', exemplu: 'Ieri am mers la parc, am întâlnit un prieten, am jucat fotbal.' },
    { mod: 'descriere', descriere: 'Prezentarea caracteristicilor unui obiect, loc sau persoană', exemplu: 'Casa era veche, cu ferestre mari și ușă de stejar.' },
    { mod: 'expunere', descriere: 'Prezentarea informațiilor și explicații în mod obiectiv', exemplu: 'Apa frige la 0°C și fierbe la 100°C.' },
    { mod: 'argumentare', descriere: 'Prezentarea de argumente pentru a convinge', exemplu: 'Să citim mai mult pentru că lectura dezvoltă imaginația.' },
    { mod: 'dialog', descriere: 'Schimb de replici între persoane', exemplu: '"Ce faci?" "Bine, și tu?" "Și eu mă descurc."' },
  ];

  const random = exemple[Math.floor(Math.random() * exemple.length)];

  return {
    tema: 'text_lectura_c6_moduri_expunere',
    mod_expunere: random.mod,
    descriere_mod: random.descriere,
    exemplu_mod: random.exemplu,
    intrebare: `Identifică modul de expunere și explică: "${random.exemplu}"`,
    raspuns_mod: random.mod,
    explicatie_descriere: random.descriere,
    tip: 'identificare_mod_expunere'
  };
}

/**
 * Generator 8: Analiză personaje literare
 * Generates exercises for literary character analysis
 */
export function generateAnalzaPersonaje() {
  const exemple = [
    { personaj: 'Hamlet', opera: 'Hamlet - Shakespeare', rol: 'protagonist', caracteristici: ['indecis', 'filozofic', 'depresiv'], evoluție: 'de la indeciziune la acțiune' },
    { personaj: 'Elizabeth Bennet', opera: 'Mândrie și Prejudecată - Austen', rol: 'protagonistă', caracteristici: ['independent', 'inteligent', 'sarcastic'], evoluție: 'de la prejudecată la înțelegere' },
    { personaj: 'Raskolnikov', opera: 'Crimă și pedeapsă - Dostoievski', rol: 'protagonist', caracteristici: ['atormentat', 'complex', 'moral'], evoluție: 'de la culpabilitate la redenție' },
    { personaj: 'Don Quixote', opera: 'Don Quixote - Cervantes', rol: 'protagonist', caracteristici: ['idealista', 'comical', 'neizbândit'], evoluție: 'constant în încercările sale' },
    { personaj: 'Dorian Gray', opera: 'Portretul lui Dorian Gray - Wilde', rol: 'protagonist', caracteristici: ['frumos', 'depravat', 'egoist'], evoluție: 'de la frumusețe la decadență' },
  ];

  const random = exemple[Math.floor(Math.random() * exemple.length)];

  return {
    tema: 'text_lectura_c6_analiza_personaje',
    personaj: random.personaj,
    opera_literara: random.opera,
    rol_personaj: random.rol,
    caracteristici: random.caracteristici,
    evoluție_personaj: random.evoluție,
    intrebare: `Analizează personajul "${random.personaj}" din "${random.opera}": rol, caracteristici și evoluție`,
    raspuns_rol: random.rol,
    raspuns_caracteristici: random.caracteristici,
    raspuns_evoluție: random.evoluție,
    tip: 'analiza_caracter_literar'
  };
}

/**
 * Generator 9: Texte nonliterare
 * Generates exercises for non-literary texts analysis
 */
export function generateTexteNonliterare() {
  const exemple = [
    { tip_text: 'articol de ziar', caracteristici: ['informativ', 'obiectiv', 'structurat'], scop: 'informare asupra unor evenimente actuale' },
    { tip_text: 'eseu', caracteristici: ['subiectiv', 'argumentativ', 'personal'], scop: 'exprimare unui punct de vedere pe o temă' },
    { tip_text: 'recenzie', caracteristici: ['evaluativ', 'critic', 'justificat'], scop: 'evaluare critică a unei opere' },
    { tip_text: 'raport științific', caracteristici: ['riguros', 'documentat', 'neutru'], scop: 'prezentarea unor descoperiri/rezultate' },
    { tip_text: 'instructiune', caracteristici: ['clar', 'secvențial', 'practic'], scop: 'ghidare în realizarea unei sarcini' },
  ];

  const random = exemple[Math.floor(Math.random() * exemple.length)];

  return {
    tema: 'text_lectura_c6_texte_nonliterare',
    tip_text: random.tip_text,
    caracteristici: random.caracteristici,
    scop_text: random.scop,
    intrebare: `Identifică tipul de text nonliterar și caracteristicile sale: ${random.caracteristici.join(', ')}`,
    raspuns_tip: random.tip_text,
    raspuns_caracteristici: random.caracteristici,
    raspuns_scop: random.scop,
    tip: 'clasificare_text_nonliterar'
  };
}

// ============================================================================
// EXPORT: Array de toți generatorii
// ============================================================================

export const allGenerators = [
  generatePolisemieOmonimie,
  generateDerivareAvansat,
  generateLocutiuni,
  generateNeologisme,
  generateCampuriSemantice,
  generateGenuriLiterare,
  generateModuriExpunere,
  generateAnalzaPersonaje,
  generateTexteNonliterare,
];

/**
 * Helper: Run random generator
 */
export function generateRandomExercise() {
  const randomGenerator = allGenerators[Math.floor(Math.random() * allGenerators.length)];
  return randomGenerator();
}
