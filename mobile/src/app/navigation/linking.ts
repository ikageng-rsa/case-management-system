import {LinkingOptions} from '@react-navigation/native';

//Handles links like legalcms://case/Case123 (e.g. from a push notification)
//about an overdue diary item or a new document) routing straight into the
//relevant case detail screen once signed in.
const linking: LinkingOptions = {
    prefixes: ['legalcms://', 'https://app.legalcms.co.za'],
    config: {
        screens: {
            Main: {
                screens:{
                    Cases:{
                        screens:{
                            CaseDetails: 'case/:caseId',
                            NarrationLog: 'case/:caseId/activity',
                            DocumentList: 'case/:caseId/documents',
                        }
                    },
                    Diary:{
                        screens:{
                            OverdueAlerts: 'diary/overdue'
                        }
                    }
                }
            }
        }
    }
};

export default linking;