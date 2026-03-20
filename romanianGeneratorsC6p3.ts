/**
 * Romanian Language Generators - C6 Level, Phase 3
 * Tema: sintaxa_c6 (5 generators) + fraza_c6 (3 generators)
 */

// ============================================================================
// TEMA: SINTAXA_C6 (5 generators)
// ============================================================================

/**
 * Generator 1: Subiect
 * Generates exercises for identifying the subject in sentences
 */
export function generateSintaxaSubiect() {
  const propozitii = [
    { prop: 'Copilul citește cartea.', subiect: 'Copilul', tip: 'substantiv' },
    { prop: 'Ei joacă în parc.', subiect: 'Ei', tip: 'pronume' },
    { prop: 'A merge pe stradă este plăcut.', subiect: 'A merge pe stradă', tip: 'infinitiv' },
    { prop: 'Frumusețea naturii te fascinează.', subiect: 'Frumusețea naturii', tip: 'grup nominal' },
    { prop: 'Să mănânci sănătos este important.', subiect: 'Să mănânci sănătos', tip: 'propoziție' },
  ];

  const random = propozitii[Math.floor(Math.random() * propozitii.length)];

  return {
    tema: 'sintaxa_c6_subiect',
    propozitie: random.prop,
    intrebare: `Identifică subiectul în propoziția: "${random.prop}"`,
    subiect_corect: random.subiect,
    tip_subiect: random.tip,
    tip: 'identificare_subiect'
  };
}

/**
 * Generator 2: Predicat verbal
 * Generates exercises for identifying verbal predicates
 */
export function generateSintaxaPredicatVerbal() {
  const propozitii = [
    { prop: 'Copilul citește cartea.', predicat: 'citește', verbe: 'citește' },
    { prop: 'Am mers la teatru ieri.', predicat: 'am mers', verbe: 'am, mers' },
    { prop: 'Vor pleca mâine dimineața.', predicat: 'vor pleca', verbe: 'vor, pleca' },
    { prop: 'Se-a întors acasă noaptea.', predicat: 'se-a întors', verbe: 'se, a, întors' },
    { prop: 'Trebuie să fac temele.', predicat: 'trebuie să fac', verbe: 'trebuie, să, fac' },
  ];

  const random = propozitii[Math.floor(Math.random() * propozitii.length)];

  return {
    tema: 'sintaxa_c6_predicat_verbal',
    propozitie: random.prop,
    intrebare: `Identifică predicatul verbal din propoziția: "${random.prop}"`,
    predicat_corect: random.predicat,
    componentele_verbale: random.verbe,
    tip: 'identificare_predicat_verbal'
  };
}

/**
 * Generator 3: Predicat nominal
 * Generates exercises for identifying nominal predicates
 */
export function generateSintaxaPredicatNominal() {
  const propozitii = [
    { prop: 'Ei sunt copii deștepți.', predicat: 'sunt copii deștepți', legatura: 'sunt', atribut: 'copii deștepți' },
    { prop: 'Aceasta pare interesantă.', predicat: 'pare interesantă', legatura: 'pare', atribut: 'interesantă' },
    { prop: 'Ion rămâne prieten cu mine.', predicat: 'rămâne prieten cu mine', legatura: 'rămâne', atribut: 'prieten cu mine' },
    { prop: 'Acum s-a făcut noaptă.', predicat: 's-a făcut noaptă', legatura: 's-a făcut', atribut: 'noaptă' },
    { prop: 'Ea era profesoare de matematică.', predicat: 'era profesoare de matematică', legatura: 'era', atribut: 'profesoare de matematică' },
  ];

  const random = propozitii[Math.floor(Math.random() * propozitii.length)];

  return {
    tema: 'sintaxa_c6_predicat_nominal',
    propozitie: random.prop,
    intrebare: `Identifică predicatul nominal și preciază legătura și atributul: "${random.prop}"`,
    predicat_corect: random.predicat,
    legatura_copulativa: random.legatura,
    atribut_predicativ: random.atribut,
    tip: 'identificare_predicat_nominal'
  };
}

/**
 * Generator 4: Complementul direct și indirect
 * Generates exercises for identifying direct and indirect objects
 */
export function generateSintaxaComplement() {
  const propozitii = [
    { prop: 'Copilul mănâncă o măr.', cd: 'o măr', cind: 'nu are', explicatie: 'CD cu acuzativ' },
    { prop: 'Profesorul dă lecția elevilor.', cd: 'lecția', cind: 'elevilor', explicatie: 'CD și CID' },
    { prop: 'Vorbesc cu Ion despre fotbal.', cd: 'nu are', cind: 'cu Ion', explicatie: 'CID cu prepoziție' },
    { prop: 'Am cumpărat o carte pentru mama.', cd: 'o carte', cind: 'pentru mama', explicatie: 'CD și CID' },
    { prop: 'Mă doare capul.', cd: 'mă', cind: 'capul', explicatie: 'CD și CID din pasiv' },
  ];

  const random = propozitii[Math.floor(Math.random() * propozitii.length)];

  return {
    tema: 'sintaxa_c6_complement',
    propozitie: random.prop,
    intrebare: `Identifică complementul direct și indirect în propoziția: "${random.prop}"`,
    complement_direct: random.cd,
    complement_indirect: random.cind,
    explicatie_model: random.explicatie,
    tip: 'identificare_complement'
  };
}

/**
 * Generator 5: Circumstanțiale și atribut
 * Generates exercises for identifying adverbial modifiers and attributes
 */
export function generateSintaxaCircumstantiale() {
  const propozitii = [
    { prop: 'Copilul merge repede pe stradă.', circumstantiale: 'repede (mod), pe stradă (loc)', atribut: 'nu are', tip_circumst: 'mod, loc' },
    { prop: 'Ieri, el a citit cartea frumoasă.', circumstantiale: 'Ieri (timp)', atribut: 'frumoasă', tip_circumst: 'timp' },
    { prop: 'Din cauza ploii, nu am ieșit afară.', circumstantiale: 'Din cauza ploii (cauză)', atribut: 'nu are', tip_circumst: 'cauză' },
    { prop: 'Copilul joacă cu mingea în parc.', circumstantiale: 'cu mingea (mijloc), în parc (loc)', atribut: 'nu are', tip_circumst: 'mijloc, loc' },
    { prop: 'Fata cântă cântece frumoase cu voce dulce.', circumstantiale: 'cu voce dulce (mod)', atribut: 'frumoase', tip_circumst: 'mod' },
  ];

  const random = propozitii[Math.floor(Math.random() * propozitii.length)];

  return {
    tema: 'sintaxa_c6_circumstantiale',
    propozitie: random.prop,
    intrebare: `Identifică circumstanțialele și atributul din propoziția: "${random.prop}"`,
    circumstantiale_identificate: random.circumstantiale,
    atribut_identificat: random.atribut,
    tipuri_circumst: random.tip_circumst,
    tip: 'identificare_circumstantiale'
  };
}

// ============================================================================
// TEMA: FRAZA_C6 (3 generators)
// ============================================================================

/**
 * Generator 6: Propoziții subordonate completive
 * Generates exercises for completive subordinate clauses
 */
export function generateFrazaSubordcomplive() {
  const exemple = [
    { principala: 'Sper că', subordonata: 'vei reușii.', intreaga: 'Sper că vei reușii.', functie: 'exprimă o speranță' },
    { principala: 'Cred că', subordonata: 'el e bolnav.', intreaga: 'Cred că el e bolnav.', functie: 'exprimă o credință' },
    { principala: 'Am auzit că', subordonata: 'au venit oaspeți.', intreaga: 'Am auzit că au venit oaspeți.', functie: 'exprimă o informație' },
    { principala: 'Mi-a spus să', subordonata: 'merg la scoală.', intreaga: 'Mi-a spus să merg la scoală.', functie: 'exprimă un ordin' },
    { principala: 'Se întreabă daca', subordonata: 'plouă mâine.', intreaga: 'Se întreabă dacă plouă mâine.', functie: 'exprimă o întrebare' },
  ];

  const random = exemple[Math.floor(Math.random() * exemple.length)];

  return {
    tema: 'fraza_c6_subord_completive',
    propozitie_principala: random.principala,
    propozitie_subordonata: random.subordonata,
    intreaga_propozitie: random.intreaga,
    intrebare: `Identifică propoziția subordonată completivă și explică funcția ei: "${random.intreaga}"`,
    raspuns_subordonata: random.subordonata,
    functia_subordonatei: random.functie,
    tip: 'identificare_subord_completiva'
  };
}

/**
 * Generator 7: Propoziții subordonate circumstanțiale
 * Generates exercises for adverbial subordinate clauses
 */
export function generateFrazaSubordCircumstantiale() {
  const exemple = [
    { principala: 'Merg la parc', subordonata: 'dacă nu plouă.', tip: 'condiție', intreaga: 'Merg la parc dacă nu plouă.' },
    { principala: 'Am plecat acasă', subordonata: 'deoarece era târziu.', tip: 'cauză', intreaga: 'Am plecat acasă deoarece era târziu.' },
    { principala: 'Deși era obosit', subordonata: 'a continuat să muncească.', tip: 'concesie', intreaga: 'Deși era obosit, a continuat să muncească.' },
    { principala: 'Când a sosit la casă', subordonata: 'a fost sigur să odihne.', tip: 'timp', intreaga: 'Când a sosit la casă, a fost sigur să odihne.' },
    { principala: 'A lucrat atât de mult', subordonata: 'încât a leșinat.', tip: 'consecință', intreaga: 'A lucrat atât de mult încât a leșinat.' },
  ];

  const random = exemple[Math.floor(Math.random() * exemple.length)];

  return {
    tema: 'fraza_c6_subord_circumstantiale',
    propozitie_principala: random.principala,
    propozitie_subordonata: random.subordonata,
    intreaga_propozitie: random.intreaga,
    tip_circumstantie: random.tip,
    intrebare: `Identifică propoziția subordonată circumstanțială și precizează tipul: "${random.intreaga}"`,
    raspuns_subordonata: random.subordonata,
    raspuns_tip: random.tip,
    tip: 'identificare_subord_circumstantiale'
  };
}

/**
 * Generator 8: Propoziții subordonate atributive
 * Generates exercises for attributive subordinate clauses
 */
export function generateFrazaSubordAtributiva() {
  const exemple = [
    { principala: 'Cartea', subordonata: 'pe care o citesc', antecedent: 'Cartea', intreaga: 'Cartea pe care o citesc este interesantă.', pronume_relativ: 'care' },
    { principala: 'Copilul', subordonata: 'cu care joc zilnic', antecedent: 'Copilul', intreaga: 'Copilul cu care joc zilnic este prieten bun.', pronume_relativ: 'care' },
    { principala: 'Orașul', subordonata: 'unde m-am născut', antecedent: 'Orașului', intreaga: 'Orașul unde m-am născut este frumos.', pronume_relativ: 'unde' },
    { principala: 'Profesorul', subordonata: 'care predă matematică', antecedent: 'Profesorul', intreaga: 'Profesorul care predă matematică este foarte strict.', pronume_relativ: 'care' },
    { principala: 'Época', subordonata: 'în care au trăit ei', antecedent: 'Época', intreaga: 'Época în care au trăit ei era pericloasă.', pronume_relativ: 'care' },
  ];

  const random = exemple[Math.floor(Math.random() * exemple.length)];

  return {
    tema: 'fraza_c6_subord_atributiva',
    propozitie_principala: random.principala,
    propozitie_subordonata: random.subordonata,
    antecedent: random.antecedent,
    intreaga_propozitie: random.intreaga,
    pronume_relativ_utilizat: random.pronume_relativ,
    intrebare: `Identifică propoziția subordonată atributivă și pronumele relativ: "${random.intreaga}"`,
    raspuns_subordonata: random.subordonata,
    raspuns_pronume: random.pronume_relativ,
    tip: 'identificare_subord_atributiva'
  };
}

// ============================================================================
// EXPORT: Array de toți generatorii
// ============================================================================

export const allGenerators = [
  generateSintaxaSubiect,
  generateSintaxaPredicatVerbal,
  generateSintaxaPredicatNominal,
  generateSintaxaComplement,
  generateSintaxaCircumstantiale,
  generateFrazaSubordcomplive,
  generateFrazaSubordCircumstantiale,
  generateFrazaSubordAtributiva,
];

/**
 * Helper: Run random generator
 */
export function generateRandomExercise() {
  const randomGenerator = allGenerators[Math.floor(Math.random() * allGenerators.length)];
  return randomGenerator();
}
