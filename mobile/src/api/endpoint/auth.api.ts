import { apiClient } from "../client";
import { Document, Resource } from "../jsonapi";
import { mapUser } from "../mappers/user";
import { User } from "@/models/index";

export interface SignInChallenge{
  challengeId: string,
  //Seconds the emailed code stays valid
  expiresIn: number;
}

export interface SignInSuccess{
  token: string;
  user: User;
}

//Step 1: Password check. The backend emails a one-time code and answers with a challenge id (no token yet).
export async function signIn(email: string, password: string, deviceName: string): Promise<SignInChallenge>{
  const {data} = await apiClient.post<Document<Resource>>('/auth/sign-in',{
    email,
    password,
    device_name: deviceName,
  });
  return {challengeId: String(data.data.id), expiresIn: Number(data.data.attributes?.expires_in ?? 600)};
}

//Step 2: The emailed code. Only now does the backend issue the bearer token (`meta.token`).
export async function verifySignIn(challengeId: string, code: string): Promise<SignInSuccess>{
  const {data} = await apiClient.post<Document<Resource>>('/auth/sign-in/verify',{
    challenge_id: challengeId,
    code
  });
  const token = data.meta?.token;
  if(!token){
    throw new Error('The server did not return a session. Please try again.');
  }
  return {token: String(token), user: mapUser(data.data)};
}

export async function fetchCurrentUser(): Promise<User>{
  const {data} = await apiClient.get<Document<Resource>>('/me');
  return mapUser(data.data);
}

//Revokes the toke server-side.
export async function logoutRequest(): Promise<void>{
  await apiClient.post('/auth/sign-out');
}