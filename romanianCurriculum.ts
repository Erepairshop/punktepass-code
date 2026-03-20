/**
 * Romanian Language Curriculum - Level C6
 * Complete registration and mapping of all C6 generators with structured learning path
 */

// ============================================================================
// IMPORTS: All C6 Generators (Phase 1-4)
// ============================================================================

import {
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
  allGenerators as generators_p1,
} from './romanianGeneratorsC6p1';

import {
  generateVerbModuriNepersonale,
  generateVerbTimpuriLiterare,
  generateVerbDiateze,
  generateVerbConjugareNeregulata,
  generateVerbAccord,
  generateAdverbTipuri,
  generatePrepozitieaCazuri,
  generateConjunctie,
  generateInterjecutie,
  allGenerators as generators_p2,
} from './romanianGeneratorsC6p2';

import {
  generateSintaxaSubiect,
  generateSintaxaPredicatVerbal,
  generateSintaxaPredicatNominal,
  generateSintaxaComplement,
  generateSintaxaCircumstantiale,
  generateFrazaSubordcomplive,
  generateFrazaSubordCircumstantiale,
  generateFrazaSubordAtributiva,
  allGenerators as generators_p3,
} from './romanianGeneratorsC6p3';

import {
  generatePolisemieOmonimie,
  generateDerivareAvansat,
  generateLocutiuni,
  generateNeologisme,
  generateCampuriSemantice,
  generateGenuriLiterare,
  generateModuriExpunere,
  generateAnalzaPersonaje,
  generateTexteNonliterare,
  allGenerators as generators_p4,
} from './romanianGeneratorsC6p4';

// ============================================================================
// LEVEL C6 DEFINITION
// ============================================================================

export const C6_LEVEL = {
  level: 6,
  name: 'C6 - Advanced Upper Intermediate',
  language: 'Romanian',
  description: 'Advanced grammar, syntax, and literary analysis for upper-intermediate learners',
  phase_count: 4,
  total_generators: 35,
  focus_areas: [
    'Complex grammatical structures',
    'Syntactic analysis and sentence construction',
    'Semantic fields and advanced vocabulary',
    'Literary and non-literary text analysis',
  ],
};

// ============================================================================
// ROMANIAN CURRICULUM STRUCTURE [LEVEL 6]
// ============================================================================

export const ROMANIAN_CURRICULUM: Record<number, any> = {
  6: {
    level: C6_LEVEL,
    phases: {
      1: {
        name: 'Phase 1: Nouns & Pronouns (Substantiv & Pronume)',
        description: 'Mastering noun declension, syntactic functions, and all pronoun types',
        generator_count: 10,
        topics: [
          {
            name: 'substantiv_c6',
            subtopics: ['declinare_completa', 'functii_sintactice', 'substantive_defective'],
            generators: [
              generateSubstantivDeclinareCompleta,
              generateSubstantivFunctiiSintactice,
              generateSubstantiveDefective,
            ],
          },
          {
            name: 'pronume_c6',
            subtopics: [
              'personal',
              'demonstrativ',
              'posesiv',
              'reflexiv',
              'interogativ',
              'nehotarât',
              'relativ',
            ],
            generators: [
              generatePronumePersonal,
              generatePronumeDemonstrativ,
              generatePronumePosesiv,
              generatePronumeReflexiv,
              generatePronumeInterogativ,
              generatePronumeNehotarat,
              generatePronumeRelativ,
            ],
          },
        ],
      },
      2: {
        name: 'Phase 2: Verbs & Function Words (Verb & Adverb-Prepoziție)',
        description: 'Non-personal verb forms, literary tenses, voice, and function words',
        generator_count: 9,
        topics: [
          {
            name: 'verb_c6',
            subtopics: [
              'moduri_nepersonale',
              'timpuri_literare',
              'diateze',
              'conjugare_neregulata',
              'acord',
            ],
            generators: [
              generateVerbModuriNepersonale,
              generateVerbTimpuriLiterare,
              generateVerbDiateze,
              generateVerbConjugareNeregulata,
              generateVerbAccord,
            ],
          },
          {
            name: 'adverb_prepozitie_c6',
            subtopics: ['adverb_tipuri', 'prepozitie_cazuri', 'conjunctie', 'interjecutie'],
            generators: [
              generateAdverbTipuri,
              generatePrepozitieaCazuri,
              generateConjunctie,
              generateInterjecutie,
            ],
          },
        ],
      },
      3: {
        name: 'Phase 3: Syntax & Complex Sentences (Sintaxă & Frază)',
        description: 'Sentence analysis, predicate types, and subordinate clauses',
        generator_count: 8,
        topics: [
          {
            name: 'sintaxa_c6',
            subtopics: [
              'subiect',
              'predicat_verbal',
              'predicat_nominal',
              'complement',
              'circumstantiale_atribut',
            ],
            generators: [
              generateSintaxaSubiect,
              generateSintaxaPredicatVerbal,
              generateSintaxaPredicatNominal,
              generateSintaxaComplement,
              generateSintaxaCircumstantiale,
            ],
          },
          {
            name: 'fraza_c6',
            subtopics: [
              'subord_completive',
              'subord_circumstantiale',
              'subord_atributiva',
            ],
            generators: [
              generateFrazaSubordcomplive,
              generateFrazaSubordCircumstantiale,
              generateFrazaSubordAtributiva,
            ],
          },
        ],
      },
      4: {
        name: 'Phase 4: Vocabulary & Text Analysis (Vocabular & Text-Lectură)',
        description: 'Semantic fields, literary genres, and comprehensive text analysis',
        generator_count: 9,
        topics: [
          {
            name: 'vocabular_c6',
            subtopics: [
              'polisemie_omonimie',
              'derivare_avansat',
              'locutiuni',
              'neologisme',
              'campuri_semantice',
            ],
            generators: [
              generatePolisemieOmonimie,
              generateDerivareAvansat,
              generateLocutiuni,
              generateNeologisme,
              generateCampuriSemantice,
            ],
          },
          {
            name: 'text_lectura_c6',
            subtopics: [
              'genuri_literare',
              'moduri_expunere',
              'analiza_personaje',
              'texte_nonliterare',
            ],
            generators: [
              generateGenuriLiterare,
              generateModuriExpunere,
              generateAnalzaPersonaje,
              generateTexteNonliterare,
            ],
          },
        ],
      },
    },
  },
};

// ============================================================================
// GENERATOR MAP [LEVEL 6]
// ============================================================================

export const GENERATOR_MAP: Record<number, Record<string, any>> = {
  6: {
    // Phase 1 Generators
    substantiv_c6_declinare_completa: {
      generator: generateSubstantivDeclinareCompleta,
      phase: 1,
      topic: 'substantiv_c6',
      subtopic: 'declinare_completa',
      difficulty: 'intermediate',
    },
    substantiv_c6_functii_sintactice: {
      generator: generateSubstantivFunctiiSintactice,
      phase: 1,
      topic: 'substantiv_c6',
      subtopic: 'functii_sintactice',
      difficulty: 'intermediate',
    },
    substantiv_c6_substantive_defective: {
      generator: generateSubstantiveDefective,
      phase: 1,
      topic: 'substantiv_c6',
      subtopic: 'substantive_defective',
      difficulty: 'advanced',
    },
    pronume_c6_personal: {
      generator: generatePronumePersonal,
      phase: 1,
      topic: 'pronume_c6',
      subtopic: 'personal',
      difficulty: 'intermediate',
    },
    pronume_c6_demonstrativ: {
      generator: generatePronumeDemonstrativ,
      phase: 1,
      topic: 'pronume_c6',
      subtopic: 'demonstrativ',
      difficulty: 'intermediate',
    },
    pronume_c6_posesiv: {
      generator: generatePronumePosesiv,
      phase: 1,
      topic: 'pronume_c6',
      subtopic: 'posesiv',
      difficulty: 'intermediate',
    },
    pronume_c6_reflexiv: {
      generator: generatePronumeReflexiv,
      phase: 1,
      topic: 'pronume_c6',
      subtopic: 'reflexiv',
      difficulty: 'intermediate',
    },
    pronume_c6_interogativ: {
      generator: generatePronumeInterogativ,
      phase: 1,
      topic: 'pronume_c6',
      subtopic: 'interogativ',
      difficulty: 'intermediate',
    },
    pronume_c6_nehotarat: {
      generator: generatePronumeNehotarat,
      phase: 1,
      topic: 'pronume_c6',
      subtopic: 'nehotarât',
      difficulty: 'intermediate',
    },
    pronume_c6_relativ: {
      generator: generatePronumeRelativ,
      phase: 1,
      topic: 'pronume_c6',
      subtopic: 'relativ',
      difficulty: 'intermediate',
    },

    // Phase 2 Generators
    verb_c6_moduri_nepersonale: {
      generator: generateVerbModuriNepersonale,
      phase: 2,
      topic: 'verb_c6',
      subtopic: 'moduri_nepersonale',
      difficulty: 'intermediate',
    },
    verb_c6_timpuri_literare: {
      generator: generateVerbTimpuriLiterare,
      phase: 2,
      topic: 'verb_c6',
      subtopic: 'timpuri_literare',
      difficulty: 'advanced',
    },
    verb_c6_diateze: {
      generator: generateVerbDiateze,
      phase: 2,
      topic: 'verb_c6',
      subtopic: 'diateze',
      difficulty: 'advanced',
    },
    verb_c6_conjugare_neregulata: {
      generator: generateVerbConjugareNeregulata,
      phase: 2,
      topic: 'verb_c6',
      subtopic: 'conjugare_neregulata',
      difficulty: 'intermediate',
    },
    verb_c6_acord: {
      generator: generateVerbAccord,
      phase: 2,
      topic: 'verb_c6',
      subtopic: 'acord',
      difficulty: 'intermediate',
    },
    adverb_c6_tipuri: {
      generator: generateAdverbTipuri,
      phase: 2,
      topic: 'adverb_prepozitie_c6',
      subtopic: 'adverb_tipuri',
      difficulty: 'intermediate',
    },
    prepozitie_c6_cazuri: {
      generator: generatePrepozitieaCazuri,
      phase: 2,
      topic: 'adverb_prepozitie_c6',
      subtopic: 'prepozitie_cazuri',
      difficulty: 'intermediate',
    },
    conjunctie_c6: {
      generator: generateConjunctie,
      phase: 2,
      topic: 'adverb_prepozitie_c6',
      subtopic: 'conjunctie',
      difficulty: 'intermediate',
    },
    interjecutie_c6: {
      generator: generateInterjecutie,
      phase: 2,
      topic: 'adverb_prepozitie_c6',
      subtopic: 'interjecutie',
      difficulty: 'beginner',
    },

    // Phase 3 Generators
    sintaxa_c6_subiect: {
      generator: generateSintaxaSubiect,
      phase: 3,
      topic: 'sintaxa_c6',
      subtopic: 'subiect',
      difficulty: 'intermediate',
    },
    sintaxa_c6_predicat_verbal: {
      generator: generateSintaxaPredicatVerbal,
      phase: 3,
      topic: 'sintaxa_c6',
      subtopic: 'predicat_verbal',
      difficulty: 'intermediate',
    },
    sintaxa_c6_predicat_nominal: {
      generator: generateSintaxaPredicatNominal,
      phase: 3,
      topic: 'sintaxa_c6',
      subtopic: 'predicat_nominal',
      difficulty: 'intermediate',
    },
    sintaxa_c6_complement: {
      generator: generateSintaxaComplement,
      phase: 3,
      topic: 'sintaxa_c6',
      subtopic: 'complement',
      difficulty: 'intermediate',
    },
    sintaxa_c6_circumstantiale: {
      generator: generateSintaxaCircumstantiale,
      phase: 3,
      topic: 'sintaxa_c6',
      subtopic: 'circumstantiale_atribut',
      difficulty: 'advanced',
    },
    fraza_c6_subord_completive: {
      generator: generateFrazaSubordcomplive,
      phase: 3,
      topic: 'fraza_c6',
      subtopic: 'subord_completive',
      difficulty: 'advanced',
    },
    fraza_c6_subord_circumstantiale: {
      generator: generateFrazaSubordCircumstantiale,
      phase: 3,
      topic: 'fraza_c6',
      subtopic: 'subord_circumstantiale',
      difficulty: 'advanced',
    },
    fraza_c6_subord_atributiva: {
      generator: generateFrazaSubordAtributiva,
      phase: 3,
      topic: 'fraza_c6',
      subtopic: 'subord_atributiva',
      difficulty: 'advanced',
    },

    // Phase 4 Generators
    vocabular_c6_polisemie_omonimie: {
      generator: generatePolisemieOmonimie,
      phase: 4,
      topic: 'vocabular_c6',
      subtopic: 'polisemie_omonimie',
      difficulty: 'advanced',
    },
    vocabular_c6_derivare_avansat: {
      generator: generateDerivareAvansat,
      phase: 4,
      topic: 'vocabular_c6',
      subtopic: 'derivare_avansat',
      difficulty: 'advanced',
    },
    vocabular_c6_locutiuni: {
      generator: generateLocutiuni,
      phase: 4,
      topic: 'vocabular_c6',
      subtopic: 'locutiuni',
      difficulty: 'intermediate',
    },
    vocabular_c6_neologisme: {
      generator: generateNeologisme,
      phase: 4,
      topic: 'vocabular_c6',
      subtopic: 'neologisme',
      difficulty: 'intermediate',
    },
    vocabular_c6_campuri_semantice: {
      generator: generateCampuriSemantice,
      phase: 4,
      topic: 'vocabular_c6',
      subtopic: 'campuri_semantice',
      difficulty: 'intermediate',
    },
    text_lectura_c6_genuri_literare: {
      generator: generateGenuriLiterare,
      phase: 4,
      topic: 'text_lectura_c6',
      subtopic: 'genuri_literare',
      difficulty: 'advanced',
    },
    text_lectura_c6_moduri_expunere: {
      generator: generateModuriExpunere,
      phase: 4,
      topic: 'text_lectura_c6',
      subtopic: 'moduri_expunere',
      difficulty: 'intermediate',
    },
    text_lectura_c6_analiza_personaje: {
      generator: generateAnalzaPersonaje,
      phase: 4,
      topic: 'text_lectura_c6',
      subtopic: 'analiza_personaje',
      difficulty: 'advanced',
    },
    text_lectura_c6_texte_nonliterare: {
      generator: generateTexteNonliterare,
      phase: 4,
      topic: 'text_lectura_c6',
      subtopic: 'texte_nonliterare',
      difficulty: 'intermediate',
    },
  },
};

// ============================================================================
// LEARNING HINTS & STRATEGIES [LEVEL 6]
// ============================================================================

export const C6_LEARNING_HINTS = {
  general: [
    'Focus on the connection between grammar and syntax - how forms relate to functions in sentences',
    'Practice identifying grammatical structures in authentic Romanian texts',
    'Keep a vocabulary journal for idioms and semantic field groups',
    'Read both literary and non-literary texts to understand different modes of discourse',
    'Pay attention to verb voice (active vs. passive) and how it affects sentence structure',
  ],
  phase_specific: {
    1: [
      'Master noun declensions in all cases before moving to compound structures',
      'Practice pronoun substitution exercises to solidify pronoun system',
      'Use contextual reading to understand defective nouns in real usage',
      'Create pronoun tables for quick reference during exercises',
    ],
    2: [
      'Learn non-personal verb forms systematically (infinitive → gerund → participle)',
      'Study literary tenses through authentic texts for better retention',
      'Practice voice transformations (active ↔ passive) with varied verbs',
      'Understand the relationship between adverbs and their adjective counterparts',
      'Learn prepositions with their typical case requirements through examples',
    ],
    3: [
      'Diagram sentences to visualize subject-predicate relationships',
      'Practice identifying complements through deletion tests (remove and check if sentence remains valid)',
      'Study subordinate clauses by their function, not just their form',
      'Create sentence trees showing relationships between main and subordinate clauses',
      'Analyze how conjunctions and relative pronouns introduce subordinate clauses',
    ],
    4: [
      'Build semantic fields by grouping related words thematically',
      'Study word derivations in families (root → derived forms)',
      'Read literary works with critical analysis of character evolution',
      'Compare narrative vs. expository texts to understand different modes of discourse',
      'Practice identifying neologisms and understanding their origins and adoption',
    ],
  },
  difficulty_progression: {
    beginner: 'Start with interjections and basic adverb types',
    intermediate:
      'Build proficiency with noun declension, pronouns, verb conjugation, and basic syntax',
    advanced:
      'Master literary tenses, voice transformations, complex subordinate clauses, and semantic analysis',
  },
  study_tips: [
    'Spend 60% time on production (writing/speaking), 40% on comprehension (reading/listening)',
    'Review defective noun patterns and irregular verb conjugations weekly',
    'Use spaced repetition for semantic fields and vocabular categories',
    'Analyze one literary text per week, identifying genre, mode of discourse, and character evolution',
    'Create personal example sentences for each grammatical structure learned',
    'Join reading clubs or discussion groups to practice modes of discourse in real contexts',
  ],
  common_mistakes_to_avoid: [
    'Mixing up similar pronouns (demonstrative vs. relative) - study their different functions',
    'Forgetting that prepositions govern specific cases - always learn them together',
    'Treating literary tenses as purely historical - they have specific narrative functions',
    'Confusing polysemy with homonymy - polysemy has semantic connection, homonymy does not',
    'Not paying attention to word order in subordinate clauses vs. main clauses',
  ],
};

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Get all generators for a specific phase
 */
export function getPhaseGenerators(phase: number): any[] {
  const phaseData = ROMANIAN_CURRICULUM[6].phases[phase];
  const generators: any[] = [];

  phaseData.topics.forEach((topic: any) => {
    generators.push(...topic.generators);
  });

  return generators;
}

/**
 * Get all generators for a specific topic
 */
export function getTopicGenerators(topic: string): any[] {
  return Object.values(GENERATOR_MAP[6])
    .filter((item: any) => item.topic === topic)
    .map((item: any) => item.generator);
}

/**
 * Get generators by difficulty level
 */
export function getGeneratorsByDifficulty(difficulty: string): any[] {
  return Object.values(GENERATOR_MAP[6])
    .filter((item: any) => item.difficulty === difficulty)
    .map((item: any) => item.generator);
}

/**
 * Get random generator from a specific phase
 */
export function getRandomPhaseGenerator(phase: number): any {
  const generators = getPhaseGenerators(phase);
  return generators[Math.floor(Math.random() * generators.length)];
}

/**
 * Get random generator from entire level
 */
export function getRandomLevelGenerator(): any {
  const allGeneratorsList = [
    ...generators_p1,
    ...generators_p2,
    ...generators_p3,
    ...generators_p4,
  ];
  return allGeneratorsList[Math.floor(Math.random() * allGeneratorsList.length)];
}

/**
 * Get curriculum summary
 */
export function getCurriculumSummary(): string {
  return `
Romanian Language - Level C6 Curriculum Summary
===============================================
Level: ${C6_LEVEL.level} - ${C6_LEVEL.name}
Total Generators: ${C6_LEVEL.total_generators}
Total Phases: ${C6_LEVEL.phase_count}

Phases:
1. Nouns & Pronouns (10 generators)
2. Verbs & Function Words (9 generators)
3. Syntax & Complex Sentences (8 generators)
4. Vocabulary & Text Analysis (9 generators)

Focus Areas:
${C6_LEVEL.focus_areas.map((area) => `  - ${area}`).join('\n')}

For detailed learning hints, see C6_LEARNING_HINTS constant.
  `;
}

// ============================================================================
// EXPORTS
// ============================================================================

export {
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
  generateVerbModuriNepersonale,
  generateVerbTimpuriLiterare,
  generateVerbDiateze,
  generateVerbConjugareNeregulata,
  generateVerbAccord,
  generateAdverbTipuri,
  generatePrepozitieaCazuri,
  generateConjunctie,
  generateInterjecutie,
  generateSintaxaSubiect,
  generateSintaxaPredicatVerbal,
  generateSintaxaPredicatNominal,
  generateSintaxaComplement,
  generateSintaxaCircumstantiale,
  generateFrazaSubordcomplive,
  generateFrazaSubordCircumstantiale,
  generateFrazaSubordAtributiva,
  generatePolisemieOmonimie,
  generateDerivareAvansat,
  generateLocutiuni,
  generateNeologisme,
  generateCampuriSemantice,
  generateGenuriLiterare,
  generateModuriExpunere,
  generateAnalzaPersonaje,
  generateTexteNonliterare,
};
