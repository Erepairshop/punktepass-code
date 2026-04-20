
export interface ILocalization {
  [key: string]: {
    title: string;
    description: string;
  };
}

export interface IMeta {
  id: string;
  title: string;
  description: string;
  localization: ILocalization;
}

export interface IQuestionOptionLocalization {
    [key: string]: {
        question: string;
        options: {
            correct: string;
            incorrect: string[];
        };
        explanation: string;
    };
}

export interface ITypingQuestionLocalization {
    [key: string]: {
        question: string;
        answer: string;
        explanation: string;
    };
}

export interface IMultipleChoice {
  question: string;
  options: {
    correct: string;
    incorrect: string[];
  };
  explanation: string;
  localization: IQuestionOptionLocalization;
}

export interface ITyping {
  question: string;
  answer: string;
  explanation: string;
  localization: ITypingQuestionLocalization;
}

export interface IQuiz {
  multipleChoice: IMultipleChoice[];
  typing: ITyping[];
}

export interface ITopic {
  meta: IMeta;
  quiz: IQuiz;
}

export interface IKnowledgeBase {
  [key: string]: ITopic;
}
