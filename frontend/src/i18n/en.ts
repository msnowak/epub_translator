import type { Messages } from './messages'

export const en: Messages = {
  'app.name': 'EPUB Translator',
  'app.signOut': 'Sign out',
  'app.language': 'Interface language',

  'common.loading': 'Loading…',
  'common.networkError': 'Could not reach the server.',
  'common.genericError': 'Something went wrong. Please try again.',
  'common.sessionTokenMissing': 'The server did not return a session token.',

  'auth.login.heading': 'Sign in',
  'auth.login.submit': 'Sign in',
  'auth.register.heading': 'Create an account',
  'auth.register.submit': 'Create an account',
  'auth.email': 'Email address',
  'auth.password': 'Password',
  'auth.noAccount': 'No account yet?',
  'auth.createOne': 'Create one',
  'auth.haveAccount': 'Already have an account?',
  'auth.signIn': 'Sign in',

  'validation.email.invalid': 'That is not a valid email address.',
  'validation.password.required': 'Enter your password.',
  'validation.password.tooShort': 'The password must be at least 8 characters long.',
  'validation.file.required': 'Choose an EPUB file.',
  'validation.title.required': 'Enter a project title.',
  'validation.targetLanguage.required': 'Choose a target language.',
  'validation.model.required': 'Choose a model.',

  'projects.list.heading': 'Your books',
  'projects.list.upload': 'Upload a book',
  'projects.list.empty': 'You have no books yet.',
  'projects.detail.back': 'All books',
  'projects.detail.detectedLanguage': 'detected automatically',
  'projects.detail.chapters': 'Chapters',
  'projects.detail.failedHeading': 'Failed paragraphs',
  'projects.detail.failedNotice': {
    one: '{count} paragraph could not be translated. You can retry it.',
    other: '{count} paragraphs could not be translated. You can retry them.',
  },

  'projects.action.start': 'Start translating',
  'projects.action.pause': 'Pause',
  'projects.action.resume': 'Resume',
  'projects.action.cancel': 'Cancel',
  'projects.action.retryFailed': 'Retry failed',
  'projects.download.preparing': 'Preparing the file…',
  'projects.download.label': 'Download the book',
  'projects.download.note':
    'You can download the book at any time — untranslated paragraphs stay in the original.',
  'projects.delete.label': 'Delete project',
  'projects.delete.confirm':
    'Delete "{title}"? The file and its translations will be gone for good.',
  'projects.delete.yes': 'Yes, delete it',
  'projects.delete.no': 'Keep it',

  'projects.progress.unknown': 'It is not yet known how many paragraphs this book has.',
  'projects.progress.count': {
    one: '{done} of {count} paragraph ({percent}%)',
    other: '{done} of {count} paragraphs ({percent}%)',
  },

  'status.parsing': 'Analysing the file',
  'status.ready': 'Ready to translate',
  'status.translating': 'Translating',
  'status.paused': 'Paused',
  'status.completed': 'Completed',
  'status.completed_with_errors': 'Completed with errors',
  'status.cancelled': 'Cancelled',
  'status.failed': 'Failed',

  'chapters.empty': 'Chapters will appear once the file has been analysed.',
  'chapters.column.chapter': 'Chapter',
  'chapters.column.translated': 'Translated',
  'chapters.column.failed': 'Failed',
  'chapters.translatedOf': '{done} of {total}',
  'chapters.numbered': 'Chapter {number}',
  'chapters.failedCount': {
    one: '{count} failed',
    other: '{count} failed',
  },

  'failed.none': 'No paragraph reported an error.',
  'failed.link': '{chapter}, paragraph {position}',

  'wizard.heading': 'Upload a book',
  'wizard.file': 'EPUB file',
  'wizard.title': 'Title',
  'wizard.sourceLanguage': 'Source language',
  'wizard.sourceLanguage.hint':
    'You can leave this blank — the model will work the language out itself.',
  'wizard.targetLanguage': 'Target language',
  'wizard.targetLanguage.hintLead': 'A language code, e.g.',
  'wizard.targetLanguage.hintTail':
    'This value goes straight into the metadata of the downloaded book.',
  'wizard.model': 'Model',
  'wizard.model.placeholder': 'Choose a model…',
  'wizard.customPrompt': 'Extra guidance for the model',
  'wizard.submit': 'Upload and create the project',

  'editor.bookFallback': 'Book',
  'editor.chapterFallback': 'Chapter',
  'editor.translatingNotice':
    'This book is still being translated — new paragraphs will not appear on their own.',
  'editor.reload': 'Load again',
  'editor.showAll': 'All paragraphs',
  'editor.showFailedOnly': 'Failed only',
  'editor.emptyFailed': 'This chapter has no failed paragraphs.',
  'editor.emptyChapter': 'This chapter has no paragraphs.',
  'editor.preview.loading': 'Loading the preview…',
  'editor.preview.title': 'Chapter preview',
  'editor.row.label': 'Translation of paragraph {position}',
  'editor.row.retranslate': 'Translate again',
  'editor.state.dirty': 'Unsaved…',
  'editor.state.saving': 'Saving…',
  'editor.state.saved': 'Saved',
  'editor.error.retranslate': 'Could not retry the translation.',
  'editor.error.status': 'Could not check the state of the paragraph.',
  'editor.error.save': 'Could not save the change.',
  'editor.error.tokens':
    'Not saved — the translation must carry the same formatting markers as the original.',

  'workerError.epub_unreadable':
    'Could not read the structure of the EPUB file. Check whether the file is damaged.',
  'workerError.ollama_unreachable_project':
    'The Ollama server is unreachable. Check that it is running, then resume the translation.',
  'workerError.ollama_unreachable_segment':
    'The Ollama server is unreachable. Check that it is running, then try again.',
  'workerError.model_invalid_translation': {
    one: 'The model did not return a valid translation of this paragraph ({count} attempt).',
    other: 'The model did not return a valid translation of this paragraph ({count} attempts).',
  },
}
