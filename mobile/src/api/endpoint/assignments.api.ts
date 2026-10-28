import { apiClient } from "../client";
import { Document, indexIncluded, Resource } from "../jsonapi";
import { mapAssignment } from "../mappers/matter";
import {CaseAssignment} from '@/models/index';

export interface AssignmentCapacity{
    id: string;
    name: string;
    //Only one person can hold an exclusion capacity (responsible, supervising).
    exclusive: boolean
}

export interface MatterParty{
    id: string;
    name: string;
    role: string;
    roleLabel: string;
    representative?: string | null;
}

export async function fetchAssignments(matterId: string): Promise<CaseAssignment[]>{
    const {data:doc} = await apiClient.get<Document<Resource[]>>(`/matters/${matterId}/assignments`);
    const included = indexIncluded(doc.included);
    return (doc.data ?? []).map(r => mapAssignment(r,included));
}

export async function assignToMatter(matterId: string, userId: string, capacity: string): Promise<CaseAssignment>{
    const {data:doc} = await apiClient.post<Document<Resource>>(`/matters/${matterId}/assignments`,{
        user_id: userId,
        capacity,
    });
    return mapAssignment(doc.data as Resource, indexIncluded(doc.included));
}

export async function removeAssignment(matterId: string, assignmentId: string): Promise<void>{
    await apiClient.delete(`/matters/${matterId}/assignments/${assignmentId}`);
}

export async function fetchCapacities(): Promise<AssignmentCapacity[]>{
    const {data:doc} = await apiClient.get<Document<Resource[]>>('/assignment-capacities');
    return (doc.data ?? []).map(r => ({
        id: String(r.id),
        name: r.attributes?.name ??'',
        role: r.attributes?.role ??'',
        roleLabel: r.attributes?.role_label ?? r.attributes?.role ?? '',
        representative: r.attributes?.representative ?? null,
        exclusive: false
    }));
}