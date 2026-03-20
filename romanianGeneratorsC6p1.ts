/**
 * Romanian Language Generators - C6 Level, Phase 1
 * Tema: substantiv_c6 (3 generators) + pronume_c6 (7 generators)
 */

// ============================================================================
// TEMA: SUBSTANTIV_C6 (3 generators)
// ============================================================================

/**
 * Generator 1: Declinare completă
 * Generates complete declination exercises for nouns (all cases, singular/plural)
 */
export function generateSubstantivDeclinareCompleta() {
  const substantive = [
    { sg: 'casa', pl: 'case', gen: 'feminine' },
    { sg: 'bărbat', pl: 'bărbați', gen: 'masculine' },
    { sg: 'copil', pl: 'copii', gen: 'masculine' },
    { sg: 'floare', pl: 'flori', gen: 'feminine' },
    { sg: 'carte', pl: 'cărți', gen: 'feminine' },
  ];

  const random = substantive[Math.floor(Math.random() * substantive.length)];
  const cases = ['nominativ', 'acuzativ', 'genitiv', 'dativ', 'vocativ', 'locativ'];

  return {
    tema: 'substantiv_c6_declinare_completa',
    cuvant: random.sg,
    plural: random.pl,
    gen: random.gen,
    caz_cerut: cases[Math.floor(Math.random() * cases.length)],
    intrebare: `Declină cuvântul "${random.sg}" în toate cazurile (singular și plural)`,
    tip: 'exercitiu_declinare'
  };
}

/**
 * Generator 2: Funcții sintactice
 * Generates exercises for identifying syntactic functions of nouns
 */
export function generateSubstantivFunctiiSintactice() {
  const propozitii = [
    { prop: 'Cartea este pe masă.', subiect: 'Cartea', functie: 'subiect' },
    { prop: 'Am cumpărat o mașină nouă.', subiect: 'mașină', functie: 'complement direct' },
    { prop: 'Vorbesc cu colegii mei.', subiect: 'colegii', functie: 'complement indirect' },
    { prop: 'Casa tatălui este mare.', subiect: 'tatălui', functie: 'complement genitivul' },
    { prop: 'Profesorul dă lecții elevilor.', subiect: 'elevilor', functie: 'complement dativ' },
  ];

  const random = propozitii[Math.floor(Math.random() * propozitii.length)];

  return {
    tema: 'substantiv_c6_functii_sintactice',
    propozitie: random.prop,
    cuvant_analizar: random.subiect,
    intrebare: `Identifică funcția sintactică a cuvântului "${random.subiect}" în propoziție`,
    functie_corecta: random.functie,
    tip: 'analiza_sintactica'
  };
}

/**
 * Generator 3: Substantive defective
 * Generates exercises for defective nouns (nouns missing certain forms)
 */
export function generateSubstantiveDefective() {
  const substantiveDefective = [
    { cuvant: 'pâine', lipsit: 'plural', explicatie: 'Se folosește doar singular' },
    { cuvant: 'credit', lipsit: 'anumite cazuri', explicatie: 'Cazul dativ nu are formă distinctă' },
    { cuvant: 'zeu', lipsit: 'plural complet', explicatie: 'Pluralul "zei" este rar' },
    { cuvant: 'persoană', lipsit: 'vocativ distinct', explicatie: 'Vocativul coincide cu nominativul' },
    { cuvant: 'moarte', lipsit: 'plural frecvent', explicatie: 'Rareori se folosește la plural' },
  ];

  const random = substantiveDefective[Math.floor(Math.random() * substantiveDefective.length)];

  return {
    tema: 'substantiv_c6_substantive_defective',
    cuvant: random.cuvant,
    caracteristica: random.lipsit,
    intrebare: `Explică de ce substantivul "${random.cuvant}" este considerat defectiv`,
    explicatie_model: random.explicatie,
    tip: 'analiza_defectiune'
  };
}

// ============================================================================
// TEMA: PRONUME_C6 (7 generators)
// ============================================================================

/**
 * Generator 4: Pronume personale
 * Generates exercises for personal pronouns
 */
export function generatePronumePersonal() {
  const persoane = [
    { pronume: 'eu', cazuri: { nominativ: 'eu', acuzativ: 'mă', genitiv: 'meu', dativ: 'mi' } },
    { pronume: 'tu', cazuri: { nominativ: 'tu', acuzativ: 'te', genitiv: 'tău', dativ: 'ți' } },
    { pronume: 'el', cazuri: { nominativ: 'el', acuzativ: 'l', genitiv: 'lui', dativ: 'i' } },
    { pronume: 'ea', cazuri: { nominativ: 'ea', acuzativ: 'o', genitiv: 'ei', dativ: 'i' } },
    { pronume: 'noi', cazuri: { nominativ: 'noi', acuzativ: 'ne', genitiv: 'nostru', dativ: 'ne' } },
  ];

  const random = persoane[Math.floor(Math.random() * persoane.length)];
  const cazuri = Object.keys(random.cazuri);
  const cazCerut = cazuri[Math.floor(Math.random() * cazuri.length)];

  return {
    tema: 'pronume_c6_personal',
    pronume_baza: random.pronume,
    caz_cerut: cazCerut,
    intrebare: `Conjugă pronumele personal "${random.pronume}" în cazul ${cazCerut}`,
    raspuns_corect: random.cazuri[cazCerut],
    tip: 'conjugare_pronume'
  };
}

/**
 * Generator 5: Pronume demonstrative
 * Generates exercises for demonstrative pronouns
 */
export function generatePronumeDemonstrativ() {
  const demonstrative = [
    { pronume: 'acesta', variante: ['aceasta', 'acestea', 'aceștia'], gen: 'masculine/feminine' },
    { pronume: 'ăcela', variante: ['aceea', 'acelea', 'aceia'], gen: 'masculine/feminine' },
    { pronume: 'asta', variante: ['aia', 'astea', 'alea'], gen: 'informal/colocvial' },
  ];

  const random = demonstrative[Math.floor(Math.random() * demonstrative.length)];

  return {
    tema: 'pronume_c6_demonstrativ',
    pronume_baza: random.pronume,
    variante: random.variante,
    intrebare: `Completează cu pronumele demonstrativ potrivit: "_____ este cartea mea"`,
    exemple_variante: random.variante,
    tip: 'completare_pronume'
  };
}

/**
 * Generator 6: Pronume posesive
 * Generates exercises for possessive pronouns
 */
export function generatePronumePosesiv() {
  const posesive = [
    { pronume: 'meu/mea', persoana: '1st singular', variante: ['al meu', 'a mea', 'ai mei', 'ale mele'] },
    { pronume: 'tău/ta', persoana: '2nd singular', variante: ['al tău', 'a ta', 'ai tăi', 'ale tale'] },
    { pronume: 'lui/ei', persoana: '3rd singular', variante: ['al lui', 'a lui', 'ai lui', 'ale lui'] },
    { pronume: 'nostru/noastră', persoana: '1st plural', variante: ['al nostru', 'a noastră', 'ai noștri', 'ale noastre'] },
    { pronume: 'vostru/voastră', persoana: '2nd plural', variante: ['al vostru', 'a voastră', 'ai voștri', 'ale voastre'] },
  ];

  const random = posesive[Math.floor(Math.random() * posesive.length)];

  return {
    tema: 'pronume_c6_posesiv',
    pronume_baza: random.pronume,
    persoana: random.persoana,
    intrebare: `Completează cu pronumele posesiv potrivit: "Aceasta este casă _____ (a mea/a ta/...)"`,
    variante: random.variante,
    tip: 'completare_pronume_posesiv'
  };
}

/**
 * Generator 7: Pronume reflexive
 * Generates exercises for reflexive pronouns
 */
export function generatePronumeReflexiv() {
  const reflexive = [
    { pronume: 'mă', verb: 'a mă spăla', propozitie: 'Mă spăl dimineața.' },
    { pronume: 'te', verb: 'a te-ți uita', propozitie: 'Te uiți în oglindă.' },
    { pronume: 'se', verb: 'a se îmbrace', propozitie: 'Se îmbracă elegant.' },
    { pronume: 'ne', verb: 'a ne-ne întâlni', propozitie: 'Ne întâlnim în parc.' },
    { pronume: 'vă', verb: 'a vă odihni', propozitie: 'Vă odihniți pe scaun.' },
  ];

  const random = reflexive[Math.floor(Math.random() * reflexive.length)];

  return {
    tema: 'pronume_c6_reflexiv',
    pronume_reflexiv: random.pronume,
    verb: random.verb,
    propozitie_exemplu: random.propozitie,
    intrebare: `Identifică pronumele reflexiv în propoziție: "${random.propozitie}"`,
    raspuns_corect: random.pronume,
    tip: 'identificare_reflexiv'
  };
}

/**
 * Generator 8: Pronume interogative
 * Generates exercises for interrogative pronouns
 */
export function generatePronumeInterogativ() {
  const interogative = [
    { pronume: 'cine', folosire: 'pentru persoane', exemplu: 'Cine este el?' },
    { pronume: 'ce', folosire: 'pentru lucruri', exemplu: 'Ce este asta?' },
    { pronume: 'care', folosire: 'pentru alegere', exemplu: 'Care este răspunsul?' },
    { pronume: 'cât/câtă', folosire: 'pentru cantitate', exemplu: 'Cât e ceasul?' },
    { pronume: 'cum', folosire: 'pentru mod', exemplu: 'Cum te cheamă?' },
  ];

  const random = interogative[Math.floor(Math.random() * interogative.length)];

  return {
    tema: 'pronume_c6_interogativ',
    pronume_interogativ: random.pronume,
    folosire: random.folosire,
    intrebare: `Completează cu pronumele interogativ potrivit: "${random.exemplu}"`,
    exemplu_corect: random.exemplu,
    tip: 'intrebari_cu_pronume'
  };
}

/**
 * Generator 9: Pronume nehotărâte
 * Generates exercises for indefinite pronouns
 */
export function generatePronumeNehotarat() {
  const nehotarate = [
    { pronume: 'cineva', intelesuri: ['o persoană oarecare', 'somebody'] },
    { pronume: 'ceva', intelesuri: ['o lucru oarecare', 'something'] },
    { pronume: 'altcineva', intelesuri: ['altă persoană', 'somebody else'] },
    { pronume: 'altceva', intelesuri: ['alt lucru', 'something else'] },
    { pronume: 'oricine', intelesuri: ['orice persoană', 'anyone'] },
    { pronume: 'orice', intelesuri: ['orice lucru', 'anything'] },
  ];

  const random = nehotarate[Math.floor(Math.random() * nehotarate.length)];

  return {
    tema: 'pronume_c6_nehotarat',
    pronume_nehotarat: random.pronume,
    intelesuri: random.intelesuri,
    intrebare: `Explică sensul pronumelui nehotărât "${random.pronume}"`,
    traducere: random.intelesuri[1],
    tip: 'explicare_pronume_nehotarat'
  };
}

/**
 * Generator 10: Pronume relative
 * Generates exercises for relative pronouns
 */
export function generatePronumeRelativ() {
  const relative = [
    { pronume: 'care', propozitie: 'Cartea pe care o citesc este interesantă.', referent: 'Cartea' },
    { pronume: 'care', propozitie: 'Copilul cu care joc este prieten.', referent: 'Copilul' },
    { pronume: 'ceea ce', propozitie: 'Ceea ce spui este adevărat.', referent: 'spui' },
    { pronume: 'cine', propozitie: 'Cine face bine, bine găsește.', referent: 'face bine' },
    { pronume: 'unde', propozitie: 'Orașul unde m-am născut este București.', referent: 'Orașul' },
  ];

  const random = relative[Math.floor(Math.random() * relative.length)];

  return {
    tema: 'pronume_c6_relativ',
    pronume_relativ: random.pronume,
    propozitie_exemplu: random.propozitie,
    referent: random.referent,
    intrebare: `Identifică pronumele relativ și antecedentul în propoziție: "${random.propozitie}"`,
    raspuns_pronume: random.pronume,
    raspuns_antecedent: random.referent,
    tip: 'identificare_relativ'
  };
}

// ============================================================================
// EXPORT: Array de toți generatorii
// ============================================================================

export const allGenerators = [
  generateSubstantivDeclinareCompleta,
  generateSubstantivFunctiiSintactice,
  generateSubstantiveDefective,
  generatePronumePersonal,
  generatePronumeDemonstrativ,
  generatePronumePosesiv,
  generatePronumeReflexiv,
  generatePronumeInterogativ,
  generatePronumeNehotarat,
  generatePronumeRelativ,
];

/**
 * Helper: Run random generator
 */
export function generateRandomExercise() {
  const randomGenerator = allGenerators[Math.floor(Math.random() * allGenerators.length)];
  return randomGenerator();
}
