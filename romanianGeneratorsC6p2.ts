/**
 * Romanian Language Generators - C6 Level, Phase 2
 * Tema: verb_c6 (5 generators) + adverb_prepozitie_c6 (4 generators)
 */

// ============================================================================
// TEMA: VERB_C6 (5 generators)
// ============================================================================

/**
 * Generator 1: Moduri nepersonale
 * Generates exercises for non-personal moods (infinitive, gerund, participle)
 */
export function generateVerbModuriNepersonale() {
  const verbe = [
    { infinitiv: 'a citi', gerunziu: 'citind', participiu: 'citit' },
    { infinitiv: 'a merge', gerunziu: 'mergând', participiu: 'mers' },
    { infinitiv: 'a scrie', gerunziu: 'scriind', participiu: 'scris' },
    { infinitiv: 'a face', gerunziu: 'făcând', participiu: 'făcut' },
    { infinitiv: 'a mânca', gerunziu: 'mâncând', participiu: 'mâncat' },
    { infinitiv: 'a dormi', gerunziu: 'dormind', participiu: 'dormit' },
  ];

  const random = verbe[Math.floor(Math.random() * verbe.length)];
  const forme = ['gerunziu', 'participiu'];
  const formaCeruta = forme[Math.floor(Math.random() * forme.length)];

  return {
    tema: 'verb_c6_moduri_nepersonale',
    infinitiv: random.infinitiv,
    intrebare: `Completează cu forma de ${formaCeruta} a verbului "${random.infinitiv}": "_____ pe stradă, l-am întâlnit pe Ion."`,
    forma_corecta: formaCeruta === 'gerunziu' ? random.gerunziu : random.participiu,
    gerunziu: random.gerunziu,
    participiu: random.participiu,
    tip: 'forme_nepersonale'
  };
}

/**
 * Generator 2: Timpuri literare
 * Generates exercises for literary tenses (plus-que-parfait, perfect simplu, viitor în trecut)
 */
export function generateVerbTimpuriLiterare() {
  const propozitii = [
    { timp: 'mai mult ca perfect', exemplu: 'Când sosii, el plecase deja.', forma: 'plecase' },
    { timp: 'perfect simplu', exemplu: 'Ieri, el citì cartea toată noaptea.', forma: 'citì' },
    { timp: 'viitor în trecut', exemplu: 'Am crezut că va veni mâine.', forma: 'va veni' },
    { timp: 'mai mult ca perfect', exemplu: 'După ce mâncase, plecă acasă.', forma: 'mâncase' },
    { timp: 'perfect simplu', exemplu: 'Socrate beu otrava cu întreg curajul.', forma: 'beu' },
  ];

  const random = propozitii[Math.floor(Math.random() * propozitii.length)];

  return {
    tema: 'verb_c6_timpuri_literare',
    timp: random.timp,
    propozitie_exemplu: random.exemplu,
    cuvant_analizar: random.forma,
    intrebare: `Identifică forma verbală și precizează ce timp literar este: "${random.forma}"`,
    raspuns_timp: random.timp,
    raspuns_forma: random.forma,
    tip: 'identificare_timp_literar'
  };
}

/**
 * Generator 3: Diateze (vocea verbului)
 * Generates exercises for voice (active, passive, reflexive/medial)
 */
export function generateVerbDiateze() {
  const exemple = [
    { activ: 'El scrie cartea.', pasiv: 'Cartea este scrisă de el.', reflexiv: 'El se-și scrie memoriile.' },
    { activ: 'Profesorul predă lecția.', pasiv: 'Lecția este predată de profesor.', reflexiv: 'Elevul și-și pregătește lecția.' },
    { activ: 'Muncitorii construiesc casa.', pasiv: 'Casa este construită de muncitori.', reflexiv: 'Ei se-și ajută reciproc.' },
    { activ: 'Vântul mișcă copacii.', pasiv: 'Copacii sunt mișcați de vânt.', reflexiv: 'Copacii se mișcă ușor.' },
    { activ: 'Doctorul vindecă pacientul.', pasiv: 'Pacientul este vindecat de doctor.', reflexiv: 'Pacientul se vindecă treptat.' },
  ];

  const random = exemple[Math.floor(Math.random() * exemple.length)];
  const diateze = ['activ', 'pasiv', 'reflexiv'];
  const diatezeAleasa = diateze[Math.floor(Math.random() * diateze.length)];

  return {
    tema: 'verb_c6_diateze',
    diatezeAleasa: diatezeAleasa,
    exemplu_activ: random.activ,
    exemplu_pasiv: random.pasiv,
    exemplu_reflexiv: random.reflexiv,
    intrebare: `Identifică diateza verbului din propoziția: "${random[diatezeAleasa as keyof typeof random]}"`,
    raspuns_correct: diatezeAleasa,
    tip: 'identificare_diateza'
  };
}

/**
 * Generator 4: Conjugare neregulată
 * Generates exercises for irregular verbs
 */
export function generateVerbConjugareNeregulata() {
  const verbeNeregulate = [
    { infinitiv: 'a fi', prezent: 'sunt/ești/este', trecut: 'eram/erai/era', participiu: 'fost' },
    { infinitiv: 'a avea', prezent: 'am/ai/are', trecut: 'aveam/aveai/avea', participiu: 'avut' },
    { infinitiv: 'a merge', prezent: 'merg/mergi/merge', trecut: 'mergeam/mergeai/mergea', participiu: 'mers' },
    { infinitiv: 'a veni', prezent: 'vin/vii/vine', trecut: 'vineam/vineai/vinea', participiu: 'venit' },
    { infinitiv: 'a face', prezent: 'fac/faci/face', trecut: 'faceam/faceai/facea', participiu: 'făcut' },
    { infinitiv: 'a duce', prezent: 'duc/duci/duce', trecut: 'duceam/duceai/ducea', participiu: 'dus' },
  ];

  const random = verbeNeregulate[Math.floor(Math.random() * verbeNeregulate.length)];
  const timpuri = ['prezent', 'trecut', 'participiu'];
  const timpCerut = timpuri[Math.floor(Math.random() * timpuri.length)];

  return {
    tema: 'verb_c6_conjugare_neregulata',
    infinitiv: random.infinitiv,
    timp_cerut: timpCerut,
    intrebare: `Conjugă verbul neregulat "${random.infinitiv}" în ${timpCerut}`,
    raspuns_corect: random[timpCerut as keyof typeof random],
    prezent: random.prezent,
    trecut: random.trecut,
    participiu: random.participiu,
    tip: 'conjugare_neregulata'
  };
}

/**
 * Generator 5: Acord verbal
 * Generates exercises for verbal agreement (subject-verb agreement)
 */
export function generateVerbAccord() {
  const exemple = [
    { subiect: 'Copilul', forma_corecta: 'joacă', forma_incorecta: 'joacă', descriere: 'sg. 3' },
    { subiect: 'Copiii', forma_corecta: 'joacă', forma_incorecta: 'joaca', descriere: 'pl. 3' },
    { subiect: 'Eu', forma_corecta: 'sunt', forma_incorecta: 'ești', descriere: 'sg. 1' },
    { subiect: 'Tu', forma_corecta: 'ești', forma_incorecta: 'sunt', descriere: 'sg. 2' },
    { subiect: 'Noi', forma_corecta: 'suntem', forma_incorecta: 'ești', descriere: 'pl. 1' },
  ];

  const random = exemple[Math.floor(Math.random() * exemple.length)];

  return {
    tema: 'verb_c6_acord',
    subiect: random.subiect,
    intrebare: `Acordă verbul cu subiectul: "${random.subiect} _____ (sunt/ești/suntem)"`,
    raspuns_corect: random.forma_corecta,
    descriere_acord: `Acord: ${random.descriere}`,
    tip: 'acord_subiect_verb'
  };
}

// ============================================================================
// TEMA: ADVERB_PREPOZITIE_C6 (4 generators)
// ============================================================================

/**
 * Generator 6: Adverb - tipuri și utilizări
 * Generates exercises for adverbs (types: manner, time, place, quantity, etc.)
 */
export function generateAdverbTipuri() {
  const adverbe = [
    { adverb: 'repede', tip: 'de mod', explicatie: 'Arată cum se face actiunea' },
    { adverb: 'ieri', tip: 'de timp', explicatie: 'Arată când se face actiunea' },
    { adverb: 'sus', tip: 'de loc', explicatie: 'Arată unde se face actiunea' },
    { adverb: 'mult', tip: 'de cantitate', explicatie: 'Arată cât de mult sau de puțin' },
    { adverb: 'poate', tip: 'de afirmație/negație', explicatie: 'Exprimă posibilitate' },
    { adverb: 'foarte', tip: 'de grad', explicatie: 'Modifică intensitatea unui adjectiv' },
  ];

  const random = adverbe[Math.floor(Math.random() * adverbe.length)];

  return {
    tema: 'adverb_c6_tipuri',
    adverb: random.adverb,
    tip_adverb: random.tip,
    intrebare: `Clasifică adverbul "${random.adverb}" și explică funcția lui`,
    raspuns_tip: random.tip,
    explicatie_model: random.explicatie,
    tip: 'clasificare_adverb'
  };
}

/**
 * Generator 7: Prepoziție - cazuri și utilizări
 * Generates exercises for prepositions and their cases
 */
export function generatePrepozitieaCazuri() {
  const prepozitii = [
    { prepozitie: 'în', cazuri: 'acuzativ/locativ', exemplu: 'Intru în casă. / Sunt în casă.' },
    { prepozitie: 'de', cazuri: 'genitiv', exemplu: 'Cartea de care vorbesc este frumoasă.' },
    { prepozitie: 'cu', cazuri: 'acuzativ/instrumental', exemplu: 'Merg cu tine la cinema.' },
    { prepozitie: 'la', cazuri: 'acuzativ/dativ', exemplu: 'Merg la teatru. / Am zis la tine să vii.' },
    { prepozitie: 'din', cazuri: 'genitiv', exemplu: 'Am venit din casă.' },
    { prepozitie: 'pe', cazuri: 'acuzativ/locativ', exemplu: 'Pun cartea pe masă. / Stau pe scaun.' },
  ];

  const random = prepozitii[Math.floor(Math.random() * prepozitii.length)];

  return {
    tema: 'prepozitie_c6_cazuri',
    prepozitie: random.prepozitie,
    cazuri_necesare: random.cazuri,
    intrebare: `Cu ce caz se folosește prepoziția "${random.prepozitie}"?`,
    raspuns_corect: random.cazuri,
    exemplu_model: random.exemplu,
    tip: 'prepoziție_caz'
  };
}

/**
 * Generator 8: Conjuncție - tipuri și funcții
 * Generates exercises for conjunctions (coordinating and subordinating)
 */
export function generateConjunctie() {
  const conjunctii = [
    { conjunctie: 'și', tip: 'coordonatoare', functie: 'Leagă propoziții sau cuvinte de același fel', exemplu: 'Ion și Maria merg la escola.' },
    { conjunctie: 'dar', tip: 'coordonatoare adversativ', functie: 'Exprimă opoziție', exemplu: 'E frumos, dar e frig.' },
    { conjunctie: 'pentru că', tip: 'subordonatoare causală', functie: 'Exprimă cauza', exemplu: 'Nu pot veni pentru că sunt bolnav.' },
    { conjunctie: 'dacă', tip: 'subordonatoare condiționată', functie: 'Exprimă condiție', exemplu: 'Dacă plouă, nu ies afară.' },
    { conjunctie: 'deși', tip: 'subordonatoare concessivă', functie: 'Exprimă concesie', exemplu: 'Deși era mic, era destul de curajos.' },
    { conjunctie: 'ca', tip: 'subordonatoare completivă', functie: 'Introduces clauze completive', exemplu: 'Sper ca tu să reușești.' },
  ];

  const random = conjunctii[Math.floor(Math.random() * conjunctii.length)];

  return {
    tema: 'conjunctie_c6',
    conjunctie: random.conjunctie,
    tip_conjunctie: random.tip,
    intrebare: `Clasifică conjuncția "${random.conjunctie}" și precizează funcția ei`,
    raspuns_tip: random.tip,
    raspuns_functie: random.functie,
    exemplu_model: random.exemplu,
    tip: 'clasificare_conjunctie'
  };
}

/**
 * Generator 9: Interjecție
 * Generates exercises for interjections
 */
export function generateInterjecutie() {
  const interjecutii = [
    { interjecutie: 'Ah!', expresie: 'de surpriză/reacție', exemplu: 'Ah, cât e de frumos!' },
    { interjecutie: 'Oh!', expresie: 'de admirație/surpriză', exemplu: 'Oh, cât de drăguț!' },
    { interjecutie: 'Oof!', expresie: 'de oboseală', exemplu: 'Oof, sunt obosit.' },
    { interjecutie: 'Nici vorbă!', expresie: 'de refuz', exemplu: 'Nici vorbă să mă duc acolo!' },
    { interjecutie: 'Bravo!', expresie: 'de apreciere', exemplu: 'Bravo, ai fost fantastic!' },
    { interjecutie: 'Vai!', expresie: 'de durere/regret', exemplu: 'Vai, ce s-a întâmplat!' },
    { interjecutie: 'Psst!', expresie: 'de atragere atenție', exemplu: 'Psst, vino aici!' },
  ];

  const random = interjecutii[Math.floor(Math.random() * interjecutii.length)];

  return {
    tema: 'interjecutie_c6',
    interjecutie: random.interjecutie,
    expresie: random.expresie,
    intrebare: `Ce sentiment exprimă interjecția "${random.interjecutie}"? Dă un exemplu de utilizare.`,
    raspuns_sentiment: random.expresie,
    exemplu_model: random.exemplu,
    tip: 'identificare_interjecutie'
  };
}

// ============================================================================
// EXPORT: Array de toți generatorii
// ============================================================================

export const allGenerators = [
  generateVerbModuriNepersonale,
  generateVerbTimpuriLiterare,
  generateVerbDiateze,
  generateVerbConjugareNeregulata,
  generateVerbAccord,
  generateAdverbTipuri,
  generatePrepozitieaCazuri,
  generateConjunctie,
  generateInterjecutie,
];

/**
 * Helper: Run random generator
 */
export function generateRandomExercise() {
  const randomGenerator = allGenerators[Math.floor(Math.random() * allGenerators.length)];
  return randomGenerator();
}
